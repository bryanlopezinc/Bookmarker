<?php

declare(strict_types=1);

namespace App\Rules;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use App\Exceptions\InvalidFolderSettingException;

final class FolderSettingsRule implements RuleContract
{
    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        try {
            FolderSettingsBuilder::fromRequest((array) json_decode($value, true))->build();
        } catch (InvalidFolderSettingException) {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function message()
    {
        return 'The given folder setting is invalid.';
    }
}
