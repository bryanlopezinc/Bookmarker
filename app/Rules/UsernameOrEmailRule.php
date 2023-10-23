<?php

declare(strict_types=1);

namespace App\Rules;

use App\ValueObjects\Username;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

final class UsernameOrEmailRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, \Closure $fail): void
    {
        $data = [$attribute => $value];

        [$isValidUsername, $isValidEmail] = [
            Validator::make($data, [$attribute => Username::rules()])->passes(),
            Validator::make($data, [$attribute => ['email']])->passes(),
        ];

        if (!$isValidEmail && !$isValidUsername) {
            $fail("The $attribute must be a valid username or email");
        }
    }
}
