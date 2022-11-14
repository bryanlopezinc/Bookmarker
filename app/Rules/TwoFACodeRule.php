<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\Invalid2FACodeException;
use App\ValueObjects\TwoFACode;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

final class TwoFACodeRule implements Rule, ValidatorAwareRule
{
    private Validator $validator;

    public function setValidator($validator)
    {
        $this->validator = $validator;

        return $this;
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
                throw new Invalid2FACodeException;
            }

            TwoFACode::fromString($value);
            return true;
        } catch (Invalid2FACodeException) {
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
