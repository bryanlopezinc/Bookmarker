<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\Invalid2FACodeException;
use App\ValueObjects\TwoFACode;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;
use Illuminate\Contracts\Validation\ValidationRule;

final class TwoFACodeRule implements ValidationRule, ValidatorAwareRule
{
    private Validator $validator;

    public function setValidator($validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, \Closure $fail): void
    {
        try {
            if (!$this->validator->validateInteger($attribute, $value)) {
                throw new Invalid2FACodeException();
            }

            TwoFACode::fromString(strval($value));
        } catch (Invalid2FACodeException) {
            $fail('Invalid verification code format');
        }
    }
}
