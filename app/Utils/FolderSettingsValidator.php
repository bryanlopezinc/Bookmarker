<?php

declare(strict_types=1);

namespace App\Utils;

use App\ValueObjects\FolderSettings;
use App\Exceptions\InvalidFolderSettingException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

final class FolderSettingsValidator
{
    /**
     * @throws InvalidFolderSettingException
     */
    public function validate(array $settings): void
    {
        $expected = FolderSettings::default();
        $keys = array_keys(Arr::dot($settings));
        $validator = Validator::make($settings, $this->rules());

        foreach ($keys as $key) {
            if (!Arr::has($expected, $key)) {
                throw new InvalidFolderSettingException("Unknown folder settings: [{$key}]", 1778);
            }
        }

        if ($validator->fails()) {
            throw new InvalidFolderSettingException(
                json_encode($validator->errors()->all(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
                1777
            );
        }
    }

    private function rules(): array
    {
        $booleanRule = function (string $attribute, mixed $value, \Closure $fail) {
            if (!is_bool($value)) {
                $fail("The {$attribute} is not a boolean value.");
            }
        };

        return [
            'version'                               => ['required', 'string', 'in:1.0.0'],
            'maxCollaboratorsLimit'                 => ['sometimes', 'int', 'min:-1', 'max:'. setting('MAX_FOLDER_COLLABORATORS_LIMIT')],
            'notifications.enabled'                  => ['sometimes', $booleanRule],
            'notifications.newCollaborator.enabled'  => ['sometimes', $booleanRule],
            'notifications.newCollaborator.mode'     => ['sometimes', 'in:*,invitedByMe'],
            'notifications.folderUpdated.enabled'    => ['sometimes', $booleanRule],
            'notifications.newBookmarks.enabled'     => ['sometimes', $booleanRule],
            'notifications.bookmarksRemoved.enabled' => ['sometimes', $booleanRule],
            'notifications.collaboratorExit.enabled' => ['sometimes', $booleanRule],
            'notifications.collaboratorExit.mode'    => ['sometimes', 'in:*,hasWritePermission'],
        ];
    }
}
