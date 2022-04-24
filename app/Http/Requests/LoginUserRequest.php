<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\TwoFA\InvalidVerificationCodeException;
use App\TwoFA\VerificationCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class LoginUserRequest extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            'with_ip' => ['ip', 'sometimes', 'filled'],
            'with_agent' => ['sometimes', 'filled'],
            'two_fa_code' => ['required', 'string', 'filled']
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function (Validator $validator) {
            if (filled($validator->failed())) {
                return;
            }

            try {
                VerificationCode::fromString($this->input('two_fa_code'));
            } catch (InvalidVerificationCodeException) {
                $validator->errors()->add('two_fa_code', 'Invalid verification code format');
            }
        });
    }
}
