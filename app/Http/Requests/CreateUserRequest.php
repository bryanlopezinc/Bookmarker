<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use App\ValueObjects\Username;
use Illuminate\Validation\Rule;

final class CreateUserRequest extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            'username'    => Username::rules(['required', Rule::unique(User::class, 'username')]),
            'firstname'   => ['required', 'filled'],
            'lastname'    => ['required', 'filled'],
            'email'       => ['required', 'email', Rule::unique(User::class, 'email')],
            'password'    => ['required', 'confirmed', Password::defaults()]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function messages()
    {
        return [
            'regex' => 'The username contains invalid characters'
        ];
    }
}
