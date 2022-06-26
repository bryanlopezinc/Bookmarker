<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesVerificationUrl
{
    protected function verificationUrlRules(): array
    {
        return [
            'verification_url' => ['required', 'url',],
        ];
    }

    protected function validateUrlAfterValidaton(Validator  $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('verification_url')) {
                return;
            }

            $verificationUrl = urldecode($this->input('verification_url'));

            foreach ([':id', ':hash', ':signature', ':expires'] as $placeHolder) {
                if (!str_contains($verificationUrl, $placeHolder)) {
                    $validator->errors()->add('verification_url', "The verification url attribute must contain $placeHolder placeholder");
                }
            }

            $this->merge(['verification_url' => $verificationUrl]);
        });
    }
}
