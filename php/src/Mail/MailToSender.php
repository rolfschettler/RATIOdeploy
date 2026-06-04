<?php

namespace Src\Mail;

/**
 * Wird bei externen mailto()-Aufrufen verwendet.
 * Kein Mailversand, kein File — bedient nur TokenController und MAIL_LOG.
 */
class MailToSender implements MailSenderInterface
{
    public function __construct()
    {
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
        // kein Versand, kein File — nur Token + MAIL_LOG werden bedient
    }

    public function getDriverName(): string
    {
        return 'mailto';
    }

}
