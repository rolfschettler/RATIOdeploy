<?php

namespace Src\Mail;

use Exception;

/**
 * Schreibt Mails als .eml-Dateien in ein Verzeichnis, statt sie
 * tatsaechlich zu versenden. Dient der Entwicklung und dem Testen.
 *
 * Die geschriebenen .eml-Dateien koennen mit jedem Mail-Client
 * (Outlook, Thunderbird) geoeffnet werden — die Mail erscheint dort
 * genau so, wie sie beim Empfaenger ankaeme.
 */
class FileMailSender implements MailSenderInterface
{
    private string $outputDir;

    public function __construct(string $outputDir)
    {
        // Verzeichnis muss existieren und beschreibbar sein
        if (!is_dir($outputDir)) {
            throw new Exception(
                "Ausgabe-Verzeichnis existiert nicht: $outputDir"
            );
        }
        if (!is_writable($outputDir)) {
            throw new Exception(
                "Keine Schreibrechte im Ausgabe-Verzeichnis: $outputDir"
            );
        }

        $this->outputDir = rtrim($outputDir, '\\/');
    }

    public function send(
        string $recipientEmail,
        ?string $recipientName,
        string $subject,
        string $bodyHtml,
        string $fromEmail,
        ?string $fromName,
        ?string $replyTo
    ): void {
        // Header-Felder zusammenbauen (RFC 2822 / RFC 5322)
        $headers = [];

        $headers[] = 'Date: ' . date('r');  // RFC 2822 date format
        $headers[] = 'From: ' . $this->formatAddress($fromEmail, $fromName);
        $headers[] = 'To: ' . $this->formatAddress($recipientEmail, $recipientName);

        if ($replyTo !== null && $replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'X-Mailer: FileMailSender (Entwicklung)';

        // Komplette Mail im EML-Format
        $eml = implode("\r\n", $headers) . "\r\n\r\n" . $bodyHtml;

        // Dateiname aus Zeitstempel + zufaelligem Suffix + sicherer Empfaenger-Adresse
        $timestamp = date('Ymd-His');
        $randomId  = substr(bin2hex(random_bytes(4)), 0, 6);
        $safeMail  = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $recipientEmail);
        $filename  = "{$timestamp}-{$randomId}-{$safeMail}.eml";
        $filepath  = $this->outputDir . DIRECTORY_SEPARATOR . $filename;

        // Datei schreiben
        $bytesWritten = file_put_contents($filepath, $eml);

        if ($bytesWritten === false) {
            throw new Exception(
                "Mail-Datei konnte nicht geschrieben werden: $filepath"
            );
        }
    }

    public function getDriverName(): string
    {
        return 'file';
    }

    /**
     * Formatiert eine Adresse als "Name <mail@example.com>" oder nur "mail@example.com".
     * Der Name wird bei Bedarf encoded (RFC 2047), falls er Sonderzeichen enthaelt.
     */
    private function formatAddress(string $email, ?string $name): string
    {
        if ($name === null || $name === '') {
            return $email;
        }
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    /**
     * Encodiert Header-Werte mit Sonderzeichen (Umlaute, etc.)
     * gemaess RFC 2047, damit Mail-Clients sie korrekt darstellen.
     */
    private function encodeHeader(string $value): string
    {
        // Falls keine Sonderzeichen drin sind: unveraendert lassen
        if (preg_match('/^[\x20-\x7E]*$/', $value)) {
            return $value;
        }
        // Sonst: Base64-Encoding mit UTF-8-Kennzeichnung
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
