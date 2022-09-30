<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\UsernameOrEmailRule;
use Illuminate\Foundation\Http\FormRequest;

final class Request2FACodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'filled', 'string', new UsernameOrEmailRule],
            'password' => ['required', 'filled']
        ];
    }
}
