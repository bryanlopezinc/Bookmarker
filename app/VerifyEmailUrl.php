<?php

declare(strict_types=1);

namespace App;

use App\ValueObjects\Url;

final class VerifyEmailUrl
{
    private string $value;

    public function __construct()
    {
        $this->value = (string) setting('EMAIL_VERIFICATION_URL');

        if (!Url::isValid($this->value)) {
            throw new \Exception('The email verification url is invalid');
        }

        foreach ([':id', ':hash', ':signature', ':expires'] as $placeHolder) {
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
