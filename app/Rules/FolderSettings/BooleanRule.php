<?php

declare(strict_types=1);

namespace App\Rules\FolderSettings;

use App\Contracts\HasHttpRuleInterface;
use Illuminate\Contracts\Validation\ValidationRule;

final class BooleanRule implements ValidationRule, HasHttpRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, \Closure $fail): void
    {
        if (!is_bool($value)) {
            $fail("The {$attribute} field must be true or false.");
        };
    }

    /**
     * {@inheritdoc}
     */
    public function getRuleForHttpInputValidation(): mixed
    {
        return 'boolean';
    }
}
