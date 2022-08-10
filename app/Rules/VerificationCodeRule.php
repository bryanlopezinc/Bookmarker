<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\InvalidVerificationCodeException;
use App\ValueObjects\VerificationCode;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

final class VerificationCodeRule implements Rule, ValidatorAwareRule
{
    private Validator $validator;

    public function setValidator($validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        try {
            if (!$this->validator->validateInteger($attribute, $value)) {
                throw new InvalidVerificationCodeException;
            }

            if (is_string($value)) {
                throw new InvalidVerificationCodeException;
            }

            new VerificationCode($value);
            return true;
        } catch (InvalidVerificationCodeException) {
            return false;
        }
    }

    /**
     * @return string|array
     */
    public function message()
    {
        return 'Invalid verification code format';
    }
}
