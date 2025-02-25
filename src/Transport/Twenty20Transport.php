<?php

namespace Twenty20\Mailer\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Twenty20\Mailer\Facades\Mailer;

class Twenty20Transport extends AbstractTransport
{
    protected Mailer $mailer;

    public function __construct(Mailer $mailer, ?LoggerInterface $logger = null)
    {
        parent::__construct($logger);
        $this->mailer = $mailer;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new \RuntimeException('Unsupported message type.');
        }

        // Extract sender and recipient
        $from = $email->getFrom()[0]->getAddress();
        $to = $email->getTo()[0]->getAddress();
        $subject = $email->getSubject();
        $body = $email->getHtmlBody() ?? $email->getTextBody();

        // Use our Mailer class to send
        $this->mailer->sendMail($to, $from, $subject, $body);
    }

    public function __toString(): string
    {
        return 'twenty20-mailer';
    }
}
