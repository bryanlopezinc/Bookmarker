<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class RequestPasswordResetRequest extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'reset_url' => ['required', 'url'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator  $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (filled($validator->failed())) {
                return;
            }

            $resetUrl = $this->input('reset_url');

            if (!str_contains($resetUrl, ':token')) {
                $validator->errors()->add('reset_url', 'The reset url attribute must contain :token placeholder');
            }

            if (!str_contains($resetUrl, ':email')) {
                $validator->errors()->add('reset_url', 'The reset url attribute must contain :email placeholder');
            }
        });
    }
}
