<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Concerns\ValidatesAttributes;
use Closure;

final class ResourceIdRule implements ValidationRule
{
    use ValidatesAttributes;

    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, Closure $fail): void
    {
        if ( ! $this->validateInteger($attribute, $value)) {
            $fail(sprintf('The %s attribute is invalid', $attribute));

            return;
        }

        if (intval($value) < 1) {
            $fail(sprintf('The %s attribute is invalid', $attribute));
        }
    }
}
