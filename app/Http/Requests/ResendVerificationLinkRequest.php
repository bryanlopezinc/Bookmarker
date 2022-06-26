<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ResendVerificationLinkRequest extends FormRequest
{
    use Concerns\ValidatesVerificationUrl;

    public function rules(): array
    {
        return $this->verificationUrlRules();
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator  $validator): void
    {
        $this->validateUrlAfterValidaton($validator);
    }
}
