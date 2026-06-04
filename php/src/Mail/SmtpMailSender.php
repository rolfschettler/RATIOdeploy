<?php

namespace Src\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Exception;

/**
 * Versendet Mails ueber einen echten SMTP-Server (z.B. Mittwald, Brevo).
 *
 * Wird automatisch verwendet, wenn in der config.ini der Eintrag
 * MAIL → driver auf 'smtp' steht.
 */
class SmtpMailSender implements MailSenderInterface
{
    private string $host;
    private int    $port;
    private string $secure;
    private string $username;
    private string $password;

    /**
     * @param string $host      SMTP-Server-Adresse
     * @param int    $port      SMTP-Port (587 fuer STARTTLS, 465 fuer SSL)
     * @param string $secure    'tls' (=STARTTLS) oder 'ssl'
     * @param string $username  SMTP-Benutzername (meist die Mail-Adresse)
     * @param string $password  SMTP-Passwort
     */
    public function __construct(
        string $host,
        int    $port,
        string $secure,
        string $username,
        string $password
    ) {
        if ($host === '') {
            throw new Exception('SMTP-Host darf nicht leer sein');
        }
        if ($port < 1 || $port > 65535) {
            throw new Exception("SMTP-Port ungueltig: $port");
        }
        if (!in_array($secure, ['tls', 'ssl'], true)) {
            throw new Exception("SMTP-Verschluesselung muss 'tls' oder 'ssl' sein, ist: $secure");
        }
        if ($username === '') {
            throw new Exception('SMTP-Benutzername darf nicht leer sein');
        }
        if ($password === '') {
            throw new Exception('SMTP-Passwort darf nicht leer sein');
        }

        $this->host     = $host;
        $this->port     = $port;
        $this->secure   = $secure;
        $this->username = $username;
        $this->password = $password;
    }

    public function send(
        string  $recipientEmail,
        ?string $recipientName,
        string  $subject,
        string  $bodyHtml,
        string  $fromEmail,
        ?string $fromName,
        ?string $replyTo
    ): void {
        // PHPMailer mit Exceptions konfigurieren — sonst gibt's nur Fehler-Codes
        $mail = new PHPMailer(true);

        try {
            // SMTP-Konfiguration
            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->Port       = $this->port;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->username;
            $mail->Password   = $this->password;
            $mail->SMTPSecure = $this->secure === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            // UTF-8 fuer Subject und Body
            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';

            // Absender und Empfaenger
            $mail->setFrom($fromEmail, $fromName ?? '');
            $mail->addAddress($recipientEmail, $recipientName ?? '');

            if ($replyTo !== null && $replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }

            // Inhalt
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $this->htmlToPlainText($bodyHtml);

            // Versenden — wirft PHPMailerException bei Fehler
            $mail->send();

        } catch (PHPMailerException $e) {
            // PHPMailer-spezifische Exception in unsere generische umwandeln
            throw new Exception(
                'SMTP-Versand fehlgeschlagen: ' . $mail->ErrorInfo,
                0,
                $e
            );
        }
    }

    public function getDriverName(): string
    {
        return 'smtp';
    }

    /**
     * Erzeugt eine einfache Plain-Text-Variante aus HTML.
     * Wird als AltBody fuer Mail-Clients ohne HTML-Anzeige genutzt.
     */
    private function htmlToPlainText(string $html): string
    {
        // Block-Elemente in Zeilenumbrueche umwandeln
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr|br)\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        // Alle restlichen Tags entfernen
        $text = strip_tags($text);

        // HTML-Entities aufloesen (&amp; → &, &uuml; → ü, etc.)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Mehrfache Leerzeilen reduzieren
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}