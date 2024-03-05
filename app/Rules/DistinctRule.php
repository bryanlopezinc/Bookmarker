<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

final class DistinctRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, \Closure $fail): void
    {
        $isUnique = collect($value)->duplicatesStrict()->isEmpty();

        if (!$isUnique) {
            $fail("The {$attribute} field has a duplicate value.");
        }
    }
}
