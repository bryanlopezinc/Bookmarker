<?php

declare(strict_types=1);

namespace App\Utils;

use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\Header\AddressHeader;

final class MailParser
{
    private readonly Message $message;

    public function __construct(string $mimeMessage)
    {
        $this->message =  Message::from($mimeMessage, true);
    }

    public function from(): string
    {
        $from = $this->message->getHeader('From');

        if ($from instanceof AddressHeader) {
            return $from->getEmail();
        }

        return '';
    }

    public function getTextContent(): ?string
    {
        return $this->message->getTextContent();
    }
}
