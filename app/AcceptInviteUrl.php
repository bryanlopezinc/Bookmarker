<?php

declare(strict_types=1);

namespace App;

use App\ValueObjects\Url;

final class AcceptInviteUrl
{
    private string $value;

    public function __construct()
    {
        $this->value = (string) setting('ACCEPT_INVITE_URL');

        if (!Url::isValid($this->value)) {
            throw new \Exception('The accept invite url is invalid');
        }

        foreach ([':invite_hash'] as $placeHolder) {
            if (!str_contains($this->value, $placeHolder)) {
                throw new \Exception("The verification url  must contain $placeHolder placeholder");
            }
        }
    }

    public function __toString()
    {
        return $this->value;
    }
}
