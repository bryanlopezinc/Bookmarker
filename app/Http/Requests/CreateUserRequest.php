<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use App\ValueObjects\Username;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CreateUserRequest extends FormRequest
{
    use Concerns\ValidatesVerificationUrl;

    public function rules(): array
    {
        return [
            'username' => Username::rules(['required', Rule::unique(User::class, 'username')]),
            'firstname'  => ['required', 'filled'],
            'lastname'  => ['required', 'filled'],
            'email' => ['required', 'email', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            ...$this->verificationUrlRules(),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator  $validator): void
    {
        $this->validateUrlAfterValidaton($validator);
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