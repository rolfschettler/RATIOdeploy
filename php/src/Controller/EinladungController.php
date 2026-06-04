<?php

namespace Src\Controller;

use Exception;

/**
 * Endpoint zum Versenden generischer Mail-Einladungen mit Token-Link.
 *
 * Wird von der Angular-Anwendung aufgerufen. Das Backend erzeugt
 * einen kryptografisch sicheren Token, hängt einen Link an den
 * uebergebenen HTML-Body und versendet die Mail.
 *
 * Die fachliche Logik (Was tut der Link beim Klick?) liegt komplett
 * in Angular. Das Backend kennt nur reference_type + reference_id
 * und reicht diese bei der Token-Einlösung wieder zurück.
 */
class EinladungController
{
    /**
     * POST /einladung/senden
     *
     * Erforderlicher Body:
     *   - email           : Empfaenger-Mail-Adresse
     *   - subject         : Mail-Betreff
     *   - body_html       : Kompletter HTML-Body. Falls der Platzhalter
     *                       {LINK} darin vorkommt, wird er durch den
     *                       generierten Link ersetzt. Sonst wird der
     *                       Link am Ende angehaengt.
     *   - reference_type  : Name der Master-Tabelle, z.B. 'GEBUCHT'
     *   - reference_id    : Primaerschluessel des Datensatzes
     *   - frontend_url    : Basis-URL der Angular-Seite, z.B.
     *                       'https://app.kunde.de/teilnehmer-formular'
     *
     * Optionale Felder:
     *   - name            : Name des Empfaengers (fuer Mail-Header)
     *   - reply_to        : Antwortadresse, falls abweichend vom Absender
     *   - valid_for_hours : Token-Gueltigkeit in Stunden (Default: 168 = 7 Tage)
     *   - single_use      : true/false (Default: false = mehrfach klickbar)
     *
     * Antwort:
     *   - log_id          : ID des Eintrags in MAIL_LOG
     *   - link            : Der erzeugte Link (zur Information / Debug)
     *
     * Wirft Exception bei ungueltigen Eingaben oder Versand-Fehlern.
     * Der Router liefert dann automatisch HTTP 500.
     */
    public function senden(): array
    {
        // Body aus Angular-Request lesen
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new Exception('Ungueltiger oder leerer JSON-Body');
        }

        // Pflichtfelder pruefen
        $required = ['email', 'subject', 'body_html', 'reference_type', 'reference_id', 'frontend_url'];
        foreach ($required as $field) {
            if (empty($data[$field]) && $data[$field] !== '0' && $data[$field] !== 0) {
                throw new Exception("Pflichtfeld '$field' fehlt oder ist leer");
            }
        }

        // Optionale Felder mit Defaults
        $name          = $data['name']            ?? null;
        $replyTo       = $data['reply_to']        ?? null;
        $validForHours = (int)($data['valid_for_hours'] ?? 168);
        $singleUse     = (bool)($data['single_use']     ?? false);

        // Frontend-URL validieren — keine offene Weiterleitung erlauben
        if (!filter_var($data['frontend_url'], FILTER_VALIDATE_URL)) {
            throw new Exception('frontend_url ist keine gueltige URL');
        }

        // Token erzeugen — der Klartext-Wert kommt in den Mail-Link
        $tokenController = new TokenController();
        $rawToken = $tokenController->create(
            'einladung',                    // purpose
            $data['reference_type'],
            (int)$data['reference_id'],
            $data['email'],
            $validForHours,
            $singleUse
        );

        // Link bauen
        $separator = strpos($data['frontend_url'], '?') === false ? '?' : '&';
        $link = $data['frontend_url'] . $separator . 't=' . $rawToken;

        // Link in den Body einbauen
        $bodyHtml = $this->insertLinkIntoBody($data['body_html'], $link);

        // Mail versenden — wirft Exception bei Fehler
        $mailController = new MailController();
        $result = $mailController->send(
            $data['email'],
            $name,
            $data['subject'],
            $bodyHtml,
            'einladung',                    // reference_type fuer mail_log
            (int)$data['reference_id'],     // reference_id fuer mail_log
            $replyTo
        );

        return [
            'log_id' => $result['log_id'],
            'link'   => $link,
        ];
    }

    /**
     * POST /einladung/oeffnen
     *
     * Wird von Angular aufgerufen, wenn der Endkunde auf den
     * Mail-Link geklickt hat. Validiert den Token und liefert
     * reference_type + reference_id zurueck, damit Angular die
     * fachlichen Daten holen kann.
     *
     * Body: { "token": "abc123..." }
     *
     * Antwort:
     *   - reference_type
     *   - reference_id
     *   - recipient_email  (zur Bestaetigung)
     *
     * Wirft Exception bei ungueltigem/abgelaufenem/eingeloestem Token.
     */
    public function oeffnen(): array
    {

   

        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['token'])) {
            throw new Exception('Pflichtfeld "token" fehlt');
        }

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;

        $tokenController = new TokenController();

        
        $info = $tokenController->redeem($data['token'], $clientIp);

        return [
            'reference_type'  => $info['reference_type'],
            'reference_id'    => $info['reference_id'],
            'recipient_email' => $info['recipient_email'],
        ];
    }

    /**
     * Setzt den generierten Link in den HTML-Body ein.
     *
     * Wenn {LINK} als Platzhalter vorkommt, wird er ersetzt.
     * Sonst wird ein Standardblock am Ende angehaengt.
     */
    private function insertLinkIntoBody(string $html, string $link): string
    {
        if (strpos($html, '{LINK}') !== false) {
            // Platzhalter ersetzen — Angular hat den Link gezielt platziert
            return str_replace('{LINK}', htmlspecialchars($link, ENT_QUOTES, 'UTF-8'), $html);
        }

        return $html;
    }
}
