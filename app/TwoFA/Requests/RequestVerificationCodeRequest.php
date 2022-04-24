<?php

declare(strict_types=1);

namespace App\TwoFA\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RequestVerificationCodeRequest extends FormRequest
{
    public function rules(): array
     {
        return [
            'username' => ['required', 'string'],
            'password' => ['required']
        ];
     }
}