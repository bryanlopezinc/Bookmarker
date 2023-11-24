<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\FolderSettingKey as Key;
use App\Utils\FolderSettingsValidator;
use Illuminate\Contracts\Support\Arrayable;

final class FolderSettings implements Arrayable
{
    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(private array $settings)
    {
        $this->settings = $settings;

        $this->ensureIsValid();
    }

    public static function make(string|FolderSettings|array $settings = null): FolderSettings
    {
        if (is_string($settings)) {
            $settings = json_decode($settings, true, flags: JSON_THROW_ON_ERROR);
        }

        if ($settings instanceof FolderSettings) {
            $settings = $settings->toArray();
        }

        return new FolderSettings($settings ?? []);
    }

    private function ensureIsValid(): void
    {
        if (empty($this->settings)) {
            return;
        }

        $validator = new FolderSettingsValidator();

        $validator->validate($this);
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function notificationsAreEnabled(): bool
    {
        return $this->get(key::ENABLE_NOTIFICATIONS);
    }

    private function get(string $key, mixed $default = true): mixed
    {
        if (!array_key_exists($key, $this->settings)) {
            return $default;
        }

        return $this->settings[$key];
    }

    public function notificationsAreDisabled(): bool
    {
        return !$this->notificationsAreEnabled();
    }

    public function newCollaboratorNotificationIsEnabled(): bool
    {
        return $this->get(key::NEW_COLLABORATOR_NOTIFICATION);
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
        return $this->get(key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION, false);
    }

    public function onlyCollaboratorsInvitedByMeNotificationIsDisabled(): bool
    {
        return !$this->onlyCollaboratorsInvitedByMeNotificationIsEnabled();
    }

    public function folderUpdatedNotificationIsEnabled(): bool
    {
        return $this->get(key::NOTIFy_ON_UPDATE);
    }

    public function folderUpdatedNotificationIsDisabled(): bool
    {
        return !$this->folderUpdatedNotificationIsEnabled();
    }

    public function newBookmarksNotificationIsEnabled(): bool
    {
        return $this->get(key::NOTIFY_ON_NEW_BOOKMARK);
    }

    public function newBookmarksNotificationIsDisabled(): bool
    {
        return !$this->newBookmarksNotificationIsEnabled();
    }

    public function bookmarksRemovedNotificationIsEnabled(): bool
    {
        return $this->get(key::NOTIFY_ON_BOOKMARK_DELETED);
    }

    public function bookmarksRemovedNotificationIsDisabled(): bool
    {
        return !$this->bookmarksRemovedNotificationIsEnabled();
    }

    public function collaboratorExitNotificationIsEnabled(): bool
    {
        return $this->get(key::NOTIFY_ON_COLLABORATOR_EXIT);
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
        return $this->get(key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION, false);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->settings;
    }
}
