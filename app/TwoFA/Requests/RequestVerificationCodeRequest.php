<?php

declare(strict_types=1);

namespace App\TwoFA\Requests;

use App\ValueObjects\Username;
use Illuminate\Foundation\Http\FormRequest;

final class RequestVerificationCodeRequest extends FormRequest
{
    public function rules(): array
     {
        return [
            'username' => Username::rules(['required']),
            'password' => ['required', 'filled']
        ];
     }
}