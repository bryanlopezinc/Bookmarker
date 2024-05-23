<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\InvalidFolderSettingException;
use App\FolderSettings\FolderSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Closure;
use Illuminate\Support\Arr;

final class FolderSettingRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, Closure $fail): void
    {
        if ( ! is_array($value)) {
            $fail("The {$attribute} must be an array.");
        }

        if (Arr::has($value, 'version')) {
            $fail('The version value is invalid.');
        }

        try {
            new FolderSettings($value);
        } catch (InvalidFolderSettingException $e) {
            $fail(Arr::first($e->errorMessages));
        }
    }
}
