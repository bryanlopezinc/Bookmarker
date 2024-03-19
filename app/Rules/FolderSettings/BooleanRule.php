<?php

declare(strict_types=1);

namespace App\Rules\FolderSettings;

use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

final class BooleanRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, Closure $fail): void
    {
        if ( ! is_bool($value)) {
            $fail("The {$attribute} field must be true or false.");
        };
    }
}
