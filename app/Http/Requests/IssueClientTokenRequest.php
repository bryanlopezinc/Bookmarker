<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IssueClientTokenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id'     => ['required', 'int'],
            'client_secret' => ['required', 'string'],
            'grant_type'    => ['required', 'string']
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
        $validator->after(function ($validator) {
            if ($this->input('grant_type') !== 'client_credentials') {
                $validator->errors()->add('grant_type', 'Invalid grant type');
            }
        });
    }
}
