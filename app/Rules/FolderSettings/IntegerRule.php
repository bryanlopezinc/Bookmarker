<?php

declare(strict_types=1);

namespace App\Rules\FolderSettings;

use App\Contracts\HasHttpRuleInterface;
use Illuminate\Contracts\Validation\ValidationRule;

final class IntegerRule implements ValidationRule, HasHttpRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, \Closure $fail): void
    {
        if (!is_int($value)) {
            $fail("The {$attribute} is not an integer value.");

            return;
        };
    }

    /**
     * {@inheritdoc}
     */
    public function getRuleForHttpInputValidation(): mixed
    {
        return 'integer';
    }
}