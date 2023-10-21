<?php

declare(strict_types=1);

namespace App\Http\Requests\SendGrid;

use Exception;
use Illuminate\Foundation\Http\FormRequest;

final class Request extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'filled'],
            'rkv'   => ['required', 'string']
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $key = 'SENDGRID_INBOUND_KEY';
        $value = env($key, fn () => throw new Exception("The $key has not been set"));

        $validator->after(function ($validator) use ($value) {
            if ($this->input('rkv') !== $value) {
                $validator->errors()->add('rkv', 'Invalid inbound key');
            }
        });
    }
}
