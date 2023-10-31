<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\UsernameOrEmailRule;
use App\Exceptions\Invalid2FACodeException;
use App\ValueObjects\TwoFACode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class LoginUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username'    => ['required', 'filled', 'string', new UsernameOrEmailRule()],
            'password'    => ['required'],
            'with_ip'     => ['ip', 'sometimes', 'filled'],
            'with_agent'  => ['sometimes', 'filled'],
            'two_fa_code' => ['sometimes', 'string', 'filled']
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
            if (filled($validator->failed()) || $this->missing('two_fa_code')) {
                return;
            }

            try {
                TwoFACode::fromString($this->input('two_fa_code'));
            } catch (Invalid2FACodeException) {
                $validator->errors()->add('two_fa_code', 'Invalid verification code format');
            }
        });
    }
}
