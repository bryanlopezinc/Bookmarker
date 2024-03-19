<?php

declare(strict_types=1);

namespace App\Rules\FolderSettings;

use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

final class IntegerRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, Closure $fail): void
    {
        if ( ! is_int($value)) {
            $fail("The {$attribute} is not an integer value.");

            return;
        };
    }
}
