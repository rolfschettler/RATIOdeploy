<?php

namespace Src\Mail;

/**
 * Gemeinsame Schnittstelle aller Mail-Versender.
 *
 * Implementierungen:
 *   - FileMailSender: schreibt Mails als .eml-Dateien (Entwicklung)
 *   - SmtpMailSender: versendet Mails ueber SMTP (Produktion, z.B. Brevo)
 *
 * Die Schnittstelle ermoeglicht den Wechsel zwischen Test- und Produktiv-
 * Versand allein durch eine Aenderung in der config.ini, ohne Code-Anpassung.
 */
interface MailSenderInterface
{
    /**
     * Versendet eine Mail.
     *
     * Wirft eine Exception bei Versand-Fehlern. Der Aufrufer ist
     * verantwortlich fuer das Logging des Erfolgs/Fehlers.
     *
     * @param string      $recipientEmail  Empfaenger-Adresse (Pflicht)
     * @param string|null $recipientName   Empfaenger-Name (optional)
     * @param string      $subject         Betreff (Pflicht)
     * @param string      $bodyHtml        HTML-Inhalt der Mail (Pflicht)
     * @param string      $fromEmail       Absender-Adresse (Pflicht)
     * @param string|null $fromName        Absender-Name (optional)
     * @param string|null $replyTo         Reply-To-Adresse (optional)
     *
     * @throws \Exception bei Versand-Fehlern
     */
    public function send(
        string $recipientEmail,
        ?string $recipientName,
        string $subject,
        string $bodyHtml,
        string $fromEmail,
        ?string $fromName,
        ?string $replyTo
    ): void;

    /**
     * Liefert einen Bezeichner des Senders fuer das Logging.
     * Wird in der MAIL_LOG-Tabelle in der Spalte DRIVER_USED gespeichert.
     *
     * @return string  z.B. 'file' oder 'smtp'
     */
    public function getDriverName(): string;
}