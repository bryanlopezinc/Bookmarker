<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Exceptions\InvalidJsonException;
use App\Utils\JsonValidator;
use Exception;
use Illuminate\Support\Arr;

final class FolderSettings
{
    /**
     * The jsonSchema that will be used to validate the folder settings.
     */
    private static string $jsonSchema = '';

    /**
     * @param array<string,string|array|bool> $settings
     */
    public function __construct(private readonly array $settings)
    {
        $this->ensureIsValid();
    }

    public static function default(): self
    {
        return new self([
            'version' => '1.0.0',
            'notifications' => [
                'enabled' => true,
                'newCollaborator' => [
                    'notify' => true,
                    'onlyCollaboratorsInvitedByMe' => false,
                ],
                'updated' => true,
                'bookmarksAdded' => true,
                'bookmarksRemoved' => true,
                'collaboratorExit' => [
                    'notify' => true,
                    'onlyWhenCollaboratorHasWritePermission' => false,
                ]
            ]
        ]);
    }

    private function ensureIsValid(): void
    {
        try {
            (new JsonValidator())->validate($this->settings, $this->getSchema());
        } catch (InvalidJsonException $e) {
            throw new Exception($e->getMessage(), 1777);
        }

        $this->ensureHasValidState();
    }

    private function getSchema(): string
    {
        if (!empty(static::$jsonSchema)) {
            return static::$jsonSchema;
        }

        $schema = file_get_contents(base_path('database/JsonSchema/folder_settings_1.0.0.json'));

        if ($schema === false) {
            throw new \Exception('could not get schema contents');
        }

        return static::$jsonSchema = $schema;
    }

    private function ensureHasValidState(): void
    {
        $errorMessages = [];

        if (
            $this->get('notifications.newCollaborator') == [
                'notify' => false,
                'onlyCollaboratorsInvitedByMe' => true,
            ]
        ) {
            $errorMessages[] = "The newCollaborator settings combination is invalid";
        }

        if (
            $this->get('notifications.collaboratorExit') == [
                'notify' => false,
                'onlyWhenCollaboratorHasWritePermission' => true,
            ]
        ) {
            $errorMessages[] = "The collaboratorExit settings combination is invalid";
        }

        if (!empty($errorMessages)) {
            throw new Exception(
                'The given settings is invalid. errors : ' . json_encode($errorMessages, JSON_PRETTY_PRINT),
                1778
            );
        }
    }

    public function notificationsAreEnabled(): bool
    {
        return $this->get('notifications.enabled');
    }

    public function notificationsAreDisabled(): bool
    {
        return !$this->notificationsAreEnabled();
    }

    public function newCollaboratorNotificationIsEnabled(): bool
    {
        return $this->get('notifications.newCollaborator.notify');
    }

    public function newCollaboratorNotificationIsDisabled(): bool
    {
        return !$this->newCollaboratorNotificationIsEnabled();
    }

    /**
     * Notify user when a new collaborator joins IF the collaborator was invited by user.
     */
    public function onlyCollaboratorsInvitedByMeNotificationIsEnabled(): bool
    {
        return $this->get('notifications.newCollaborator.onlyCollaboratorsInvitedByMe');
    }

    public function onlyCollaboratorsInvitedByMeNotificationIsDisabled(): bool
    {
        return !$this->onlyCollaboratorsInvitedByMeNotificationIsEnabled();
    }

    public function folderUpdatedNotificationIsEnabled(): bool
    {
        return $this->get('notifications.updated');
    }

    public function folderUpdatedNotificationIsDisabled(): bool
    {
        return !$this->folderUpdatedNotificationIsEnabled();
    }

    public function newBookmarksNotificationIsEnabled(): bool
    {
        return $this->get('notifications.bookmarksAdded');
    }

    public function newBookmarksNotificationIsDisabled(): bool
    {
        return !$this->newBookmarksNotificationIsEnabled();
    }

    public function bookmarksRemovedNotificationIsEnabled(): bool
    {
        return $this->get('notifications.bookmarksRemoved');
    }

    public function bookmarksRemovedNotificationIsDisabled(): bool
    {
        return !$this->bookmarksRemovedNotificationIsEnabled();
    }

    public function collaboratorExitNotificationIsEnabled(): bool
    {
        return $this->get('notifications.collaboratorExit.notify');
    }

    public function collaboratorExitNotificationIsDisabled(): bool
    {
        return !$this->collaboratorExitNotificationIsEnabled();
    }

    /**
     * Notify user when collaborator leaves IF the collaborator had any write permission
     */
    public function onlyCollaboratorWithWritePermissionNotificationIsEnabled(): bool
    {
        return $this->get('notifications.collaboratorExit.onlyWhenCollaboratorHasWritePermission');
    }

    private function get(string $key): mixed
    {
        return Arr::get($this->settings, $key, fn () => throw new Exception('Invalid key ' . $key));
    }

    /**
     * @return array<string,string|array|bool>
     */
    public function toArray(): array
    {
        return $this->settings;
    }
}
