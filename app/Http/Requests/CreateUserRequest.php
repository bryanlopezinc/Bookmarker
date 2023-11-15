<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SecondaryEmail;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use App\ValueObjects\Username;
use Illuminate\Validation\Rule;

final class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username'   => Username::rules(['bail', 'required', Rule::unique(User::class, 'username')]),
            'first_name' => ['required', 'filled', 'max:100'],
            'last_name'  => ['required', 'filled', 'max:100'],
            'email'      => [
                'bail',
                'required',
                'email',
                Rule::unique(User::class, 'email'),
                Rule::unique(SecondaryEmail::class, 'email')
            ],
            'password'   => ['required', 'confirmed', Password::defaults()],
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
