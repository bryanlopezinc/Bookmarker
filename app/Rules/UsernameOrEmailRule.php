<?php

declare(strict_types=1);

namespace App\Rules;

use App\ValueObjects\Username;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;

final class UsernameOrEmailRule implements Rule
{
    protected string $message;
    protected string $attribute;

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->attribute = $attribute;

        $data = [$attribute => $value];

        $failedValidations = array_filter([
            Validator::make($data, [$attribute => Username::rules()])->fails(),
            Validator::make($data, [$attribute => ['email']])->fails(),
        ]);

        return count($failedValidations) < 2;
    }

    /**
     * @return string
     */
    public function message()
    {
        return str_replace(':attribute', $this->attribute, 'The :attribute must be a valid username or email');
    }
}
