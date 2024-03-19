<?php

declare(strict_types=1);

namespace App\Rules;

use App\ValueObjects\TwoFACode;
use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

final class TwoFACodeRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, Closure $fail): void
    {
        if ( ! TwoFACode::isValid($value)) {
            $fail('Invalid verification code format');
        }
    }
}
