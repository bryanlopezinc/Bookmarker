<?php

declare(strict_types=1);

namespace App\Rules;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Exceptions\InvalidFolderSettingException;

final class FolderSettingsRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, \Closure $fail): void
    {
        try {
            FolderSettingsBuilder::fromRequest((array) json_decode($value, true))->build();
        } catch (InvalidFolderSettingException) {
            $fail('The given folder setting is invalid');
        }
    }
}
