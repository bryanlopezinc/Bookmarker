<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\FolderSettingKey as Key;
use App\Utils\FolderSettingsValidator;
use Illuminate\Contracts\Support\Arrayable;

final class FolderSettings implements Arrayable
{
    /**
     * @var array<int,mixed> $settings
     */
    private array $settings;

    /**
     * @param array<int,mixed> $settings
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;

        $this->ensureIsValid();
    }

    public static function fromQuery(iterable $result): self
    {
        return new self(
            collect($result)
                ->mapWithKeys(fn (array $setting) => [$setting['key'] => boolval($setting['value'])])
                ->all()
        );
    }

    private function ensureIsValid(): void
    {
        if (empty($this->settings)) {
            return;
        }

        $validator = new FolderSettingsValidator();

        $validator->validate($this);

        $validator->ensureValidState($this);
    }

    public function notificationsAreEnabled(): bool
    {
        return $this->settings[key::ENABLE_NOTIFICATIONS->value] ?? true;
    }

    public function notificationsAreDisabled(): bool
    {
        return !$this->notificationsAreEnabled();
    }

    public function newCollaboratorNotificationIsEnabled(): bool
    {
        return $this->settings[key::NEW_COLLABORATOR_NOTIFICATION->value] ?? true;
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
        return $this->settings[key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION->value] ?? false;
    }

    public function onlyCollaboratorsInvitedByMeNotificationIsDisabled(): bool
    {
        return !$this->onlyCollaboratorsInvitedByMeNotificationIsEnabled();
    }

    public function folderUpdatedNotificationIsEnabled(): bool
    {
        return $this->settings[key::NOTIFy_ON_UPDATE->value] ?? true;
    }

    public function folderUpdatedNotificationIsDisabled(): bool
    {
        return !$this->folderUpdatedNotificationIsEnabled();
    }

    public function newBookmarksNotificationIsEnabled(): bool
    {
        return $this->settings[key::NOTIFY_ON_NEW_BOOKMARK->value] ?? true;
    }

    public function newBookmarksNotificationIsDisabled(): bool
    {
        return !$this->newBookmarksNotificationIsEnabled();
    }

    public function bookmarksRemovedNotificationIsEnabled(): bool
    {
        return $this->settings[key::NOTIFY_ON_BOOKMARK_DELETED->value] ?? true;
    }

    public function bookmarksRemovedNotificationIsDisabled(): bool
    {
        return !$this->bookmarksRemovedNotificationIsEnabled();
    }

    public function collaboratorExitNotificationIsEnabled(): bool
    {
        return $this->settings[key::NOTIFY_ON_COLLABORATOR_EXIT->value] ?? true;
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
        return $this->settings[key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION->value] ?? false;
    }

    /**
     * @return array<string,string|array|bool>
     */
    public function toArray(): array
    {
        return $this->settings;
    }
}
