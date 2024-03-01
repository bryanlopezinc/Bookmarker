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
        $validator = Validator::make($settings, $this->rules());

        $this->validateKeys($settings);

        if ($validator->fails()) {
            throw new InvalidFolderSettingException(
                json_encode($validator->errors()->all(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
                1777
            );
        }
    }

    private function validateKeys(array $settings): void
    {
        $expected = FolderSettings::default();

        //reset keys that accept array to prevent "key.0" type value
        foreach (['acceptInviteConstraints'] as $setting) {
            if (Arr::has($settings, $setting)) {
                $settings[$setting] = [];
            }
        }

        foreach (array_keys(Arr::dot($settings)) as $key) {
            if (!Arr::has($expected, $key)) {
                throw new InvalidFolderSettingException("Unknown folder settings: [{$key}]", 1778);
            }
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
            'maxCollaboratorsLimit'                 => ['sometimes', 'int', 'min:-1', 'max:' . setting('MAX_FOLDER_COLLABORATORS_LIMIT')],
            'acceptInviteConstraints'               => ['sometimes', 'array', 'distinct:strict', 'in:InviterMustBeAnActiveCollaborator,InviterMustHaveRequiredPermission'],
            'acceptInviteConstraints.*'             => ['distinct:strict'],
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
