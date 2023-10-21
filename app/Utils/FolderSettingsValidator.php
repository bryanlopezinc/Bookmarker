<?php

declare(strict_types=1);

namespace App\Utils;

use App\DataTransferObjects\FolderSettings;
use App\Exceptions\InvalidJsonException;
use App\Enums\FolderSettingKey as Key;
use App\Exceptions\InvalidFolderSettingException;

final class FolderSettingsValidator
{
    private static ?string $jsonSchema = null;

    private JsonValidator $jsonValidator;

    public function __construct(JsonValidator $jsonValidator = null)
    {
        $this->jsonValidator = $jsonValidator ?: new JsonValidator;

        if (self::$jsonSchema === null) {
            self::$jsonSchema = json_encode($this->getSchema(), JSON_THROW_ON_ERROR);
        }
    }

    private function getSchema(): array
    {
        return [
            //'$schema'              => 'http://json-schema.org/draft-07/schema#',
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties' => [
                Key::ENABLE_NOTIFICATIONS->value                                       => ['type' => 'boolean'],
                Key::NEW_COLLABORATOR_NOTIFICATION->value                              => ['type' => 'boolean'],
                Key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION->value             => ['type' => 'boolean'],
                Key::NOTIFy_ON_UPDATE->value                                           => ['type' => 'boolean'],
                Key::NOTIFY_ON_NEW_BOOKMARK->value                                     => ['type' => 'boolean'],
                Key::NOTIFY_ON_COLLABORATOR_EXIT->value                                => ['type' => 'boolean'],
                Key::NOTIFY_ON_COLLABORATOR_EXIT->value                                => ['type' => 'boolean'],
                Key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION->value => ['type' => 'boolean'],
                Key::NOTIFY_ON_BOOKMARK_DELETED->value                                 => ['type' => 'boolean'],
            ],
        ];
    }

    /**
     * @throws InvalidFolderSettingException
     */
    public function validate(FolderSettings $settings): void
    {
        try {
            $this->jsonValidator->validate($settings->toArray(), self::$jsonSchema);
        } catch (InvalidJsonException $th) {
            throw new InvalidFolderSettingException($th->getMessage(), 1777);
        }
    }

    /**
     * @throws InvalidFolderSettingException
     */
    public function ensureValidState(FolderSettings $settings): void
    {
        $errorMessages = [];

        if (
            !$settings->newCollaboratorNotificationIsEnabled()
            && $settings->onlyCollaboratorsInvitedByMeNotificationIsEnabled()
        ) {
            $errorMessages[] = "The newCollaborator settings combination is invalid";
        }

        if (
            $settings->collaboratorExitNotificationIsDisabled()
            && $settings->onlyCollaboratorWithWritePermissionNotificationIsEnabled()
        ) {
            $errorMessages[] = "The collaboratorExit settings combination is invalid";
        }

        if (!empty($errorMessages)) {
            throw new InvalidFolderSettingException(
                'The given settings is invalid. errors : ' . json_encode($errorMessages, JSON_PRETTY_PRINT),
                1778
            );
        }
    }
}
