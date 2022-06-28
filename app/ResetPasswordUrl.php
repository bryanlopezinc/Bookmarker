<?php

declare(strict_types=1);

namespace App;

use App\ValueObjects\Url;

final class ResetPasswordUrl
{
    private string $value;

    public function __construct()
    {
        $this->value = (string) setting('RESET_PASSWORD_URL');

        if (!Url::isValid($this->value)) {
            throw new \Exception('The reset password url is invalid');
        }

        foreach ([':token', ':email'] as $placeHolder) {
            if (!str_contains($this->value, $placeHolder)) {
                throw new \Exception("The reset password url  must contain the $placeHolder placeholder");
            }
        }
    }

    public function __toString()
    {
        return $this->value;
    }
}
