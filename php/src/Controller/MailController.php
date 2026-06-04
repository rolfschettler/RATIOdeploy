<?php

namespace Src\Controller;

use Core\Config;
use Src\Mail\FileMailSender;
use Src\Mail\MailToSender;
use Src\Mail\MailSenderInterface;
use Exception;
use Src\Mail\SmtpMailSender;
use DateTimeImmutable;

/**
 * Zentrale Klasse fuer Mail-Versand.
 *
 * Versendet Mails synchron (kein Queue-Mechanismus, kein Worker)
 * und protokolliert jeden Versand-Versuch in der Tabelle MAIL_LOG.
 *
 * Verwendung:
 *   $mc = new MailController();
 *   $result = $mc->send(
 *       'kunde@example.com',
 *       'Anna Müller',
 *       'Ihre Anmeldung',
 *       '<p>Hallo Frau Müller, ...</p>',
 *       'auftrag', 12345
 *   );
 *   if ($result['success']) { ... }
 */
class MailController
{

    /**
     * Versendet eine Mail und protokolliert das Ergebnis.
     *
     * Wirft im Fehlerfall eine Exception, die der Router automatisch
     * als HTTP 500 mit Fehlermeldung an den Aufrufer weiterleitet.
     * Das Mail-Log wird in jedem Fall geschrieben (Erfolg ODER Fehler),
     * sodass der Versuch nachvollziehbar bleibt.
     *
     * @return array  ['success' => true, 'log_id' => int]
     *
     * @throws Exception  bei Versand-Fehlern oder ungueltigen Eingaben
     */
    public function send(
        string  $recipientEmail,
        ?string $recipientName,
        string  $subject,
        string  $bodyHtml,
        ?string $referenceType = null,
        ?int    $referenceId = null,
        ?string $replyTo = null
    ): array {
        // Eingabe-Validierung — fliegt direkt an den Aufrufer durch
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Ungueltige Empfaenger-Adresse: $recipientEmail");
        }
        if ($subject === '') {
            throw new Exception("Betreff darf nicht leer sein");
        }
        if ($bodyHtml === '') {
            throw new Exception("Mail-Inhalt darf nicht leer sein");
        }
        if ($replyTo !== null && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Ungueltige Reply-To-Adresse: $replyTo");
        }

        // Absender aus config.ini holen
        $fromEmail = Config::get('MAIL', 'from_address', '');
        $fromName  = Config::get('MAIL', 'from_name', '');

        if ($fromEmail === '') {
            throw new Exception("Kein Absender (from_address) in config.ini konfiguriert");
        }

        // Sender erzeugen
        $sender = $this->createSender();

        // Versand durchfuehren — bei Fehler wird die Exception nach dem
        // Logging weitergeworfen, damit sie der Router an Angular liefert
        try {
            $sender->send(
                $recipientEmail,
                $recipientName,
                $subject,
                $bodyHtml,
                $fromEmail,
                $fromName,
                $replyTo
            );
        } catch (Exception $e) {
            // Erst den Fehler ins Log schreiben — Logging-Fehler ignorieren,
            // damit der eigentliche Fehler nicht ueberdeckt wird
            try {
                $this->writeLog(
                    $recipientEmail,
                    $recipientName,
                    $subject,
                    $bodyHtml,
                    $fromEmail,
                    $fromName,
                    $replyTo,
                    false,
                    $e->getMessage(),
                    $sender->getDriverName(),
                    $referenceType,
                    $referenceId
                );
            } catch (Exception $logException) {
                // Logging-Fehler verschlucken — kommt im naechsten Schritt
                // ins zentrale Error-Log (machen wir spaeter)
            }

            // Originalen Fehler weiterwerfen
            throw $e;
        }

        // Erfolg: Log schreiben und Erfolgsmeldung zurueckgeben
        $logId = $this->writeLog(
            $recipientEmail,
            $recipientName,
            $subject,
            $bodyHtml,
            $fromEmail,
            $fromName,
            $replyTo,
            true,
            null,
            $sender->getDriverName(),
            $referenceType,
            $referenceId
        );

        return ['success' => true, 'log_id' => $logId];
    }


/**
 * Erzeugt den passenden MailSender anhand der Konfiguration.
 */
private function createSender(): MailSenderInterface
{
    $driver = Config::get('MAIL', 'driver', 'file');

    switch ($driver) {
        case 'file':
            $outputDir = Config::get('MAIL', 'file_output_dir');
            if (!$outputDir) {
                throw new Exception("file_output_dir nicht in config.ini gesetzt");
            }
            return new FileMailSender($outputDir);

        case 'smtp':
            $host     = Config::get('MAIL', 'smtp_host', '');
            $port     = (int)Config::get('MAIL', 'smtp_port', 587);
            $secure   = Config::get('MAIL', 'smtp_secure', 'tls');
            $username = Config::get('MAIL', 'smtp_user', '');
            $password = Config::get('MAIL', 'smtp_pass', '');

            return new SmtpMailSender($host, $port, $secure, $username, $password);

        case 'mailto':
            return new MailToSender();


        default:
            throw new Exception("Unbekannter Mail-Driver: $driver");
    }
}



    /**
     * Schreibt einen Eintrag in die MAIL_LOG-Tabelle.
     * Liefert die ID des neuen Eintrags zurueck (durch Suche, da Interbase
     * kein RETURNING hat — wir suchen nach der gerade geschriebenen Zeile).
     */
    private function writeLog(
        string  $recipientEmail,
        ?string $recipientName,
        string  $subject,
        string  $bodyHtml,
        string  $fromEmail,
        ?string $fromName,
        ?string $replyTo,
        bool    $success,
        ?string $errorMessage,
        string  $driverUsed,
        ?string $referenceType,
        ?int    $referenceId
    ): int {
        // Benutzer ermitteln, falls ein Login aktiv ist
        $sentByUser = $_SERVER['APP_AUTH']['user'] ?? null;

        // Eindeutiger Marker, mit dem wir den gerade eingefuegten Eintrag wiederfinden
        // (weil Interbase keine RETURNING-Klausel hat)
        $marker = 'log_' . bin2hex(random_bytes(8));

        $delphi = new DelphiApiController();

        // Schreiben — wir missbrauchen ERROR_MESSAGE temporaer als Marker-Traeger
        // Nein, das ist hässlich. Sauberer Weg: Einfügen, dann SENT_AT als Suchkriterium nutzen
        $insertData = [
            'recipient_email' => $recipientEmail,
            'recipient_name'  => $recipientName,
            'subject'         => $subject,
            'body_html'       => $bodyHtml,
            'from_email'      => $fromEmail,
            'from_name'       => $fromName,
            'reply_to'        => $replyTo,
            'success'         => $success ? 1 : 0,
            'error_message'   => $errorMessage,
            'driver_used'     => $driverUsed,
            'sent_by_user'    => $sentByUser,
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
        ];

        $delphi->insert('mail_log', json_encode($insertData));

        // ID des gerade eingefuegten Eintrags ermitteln:
        // Wir nehmen den juengsten Eintrag fuer diesen Empfaenger, der gerade entstanden ist
        $result = $delphi->select(json_encode([
            'sql'    => 'select max(id) as max_id from mail_log
                         where recipient_email = :email',
            'params' => ['email' => $recipientEmail]
        ]));

        $row = array_change_key_case($result['data'][0] ?? [], CASE_LOWER);
        return (int)($row['max_id'] ?? 0);
    }

    /**
     * Liefert die letzten N Mails (fuer Admin-Oberflaechen).
     */

    public function getRecent(int $limit = 50): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new Exception('limit muss zwischen 1 und 1000 liegen');
        }

        $delphi = new DelphiApiController();
        $result = $delphi->select(json_encode([
            'sql'    => 'select id, recipient_email, recipient_name, subject,
                            success, error_message, driver_used,
                            sent_at, sent_by_user,
                            reference_type, reference_id
                     from mail_log
                     order by sent_at desc
                     rows :limit',
            'params' => ['limit' => $limit]
        ]));

        $rows = $result['data'] ?? [];
        return array_map(fn($r) => array_change_key_case($r, CASE_LOWER), $rows);
    }


    /**
     * Liefert alle Mails zu einem fachlichen Bezug.
     */
    public function getByReference(string $referenceType, int $referenceId): array
    {
        $delphi = new DelphiApiController();
        $result = $delphi->select(json_encode([
            'sql'    => 'select id, recipient_email, recipient_name, subject,
                                success, error_message, sent_at, sent_by_user
                         from mail_log
                         where reference_type = :type and reference_id = :id
                         order by sent_at desc',
            'params' => ['type' => $referenceType, 'id' => $referenceId]
        ]));

        $rows = $result['data'] ?? [];
        return array_map(fn($r) => array_change_key_case($r, CASE_LOWER), $rows);
    }
}
