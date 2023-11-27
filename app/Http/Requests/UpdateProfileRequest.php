<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        $baseRules = (new CreateUserRequest())->rules();

        return [
            'first_name'   => array_filter(['required_without_all:last_name,two_fa_mode,password,profile_photo',...$baseRules['first_name']], fn ($rule) => $rule !== 'required'),
            'last_name'    => array_filter($baseRules['last_name'], fn ($rule) => $rule !== 'required'),
            'two_fa_mode'  => ['sometimes', 'string', 'in:none,email'],
            'password'     => ['sometimes', 'confirmed', Password::defaults()],
            'old_password' => ['required_with:password', 'current_password'],
            'profile_photo' => $baseRules['profile_photo']
        ];
    }
}
