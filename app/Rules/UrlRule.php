<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\MalformedURLException;
use App\ValueObjects\Url;
use Illuminate\Contracts\Validation\ValidationRule;

final class UrlRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, \Closure $fail): void
    {
        $message = "The $attribute must be a valid url";

        if (!is_string($value)) {
            $fail($message);

            return;
        }

        try {
            new Url($value);
        } catch (MalformedURLException) {
            $fail($message);
        }
    }
}
