<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\ValueObjects\FolderSettings;
use App\Enums\FolderSettingKey as Key;

final class FolderSettingsBuilder
{
    /**
     * @param array<mixed> $attributes
     */
    public function __construct(protected array $attributes = [])
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public static function new(): FolderSettingsBuilder
    {
        return new FolderSettingsBuilder;
    }

    public static function fromRequest(array $data): self
    {
        $settings = [];

        $map = [
            'enable_notifications'                   => Key::ENABLE_NOTIFICATIONS,
            'notify_on_new_collaborator'             => Key::NEW_COLLABORATOR_NOTIFICATION,
            'notify_on_new_collaborator_by_user'     => Key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION,
            'notify_on_update'                       => Key::NOTIFy_ON_UPDATE,
            'notify_on_new_bookmark'                 => Key::NOTIFY_ON_NEW_BOOKMARK,
            'notify_on_bookmark_delete'              => Key::NOTIFY_ON_BOOKMARK_DELETED,
            'notify_on_collaborator_exit'            => Key::NOTIFY_ON_COLLABORATOR_EXIT,
            'notify_on_collaborator_exit_with_write' => Key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION // phpcs:ignore
        ];

        foreach ($data as $key => $value) {
            $key = $map[$key] ?? $key;
            $settings[$key] = $value;
        }

        return new self($settings);
    }

    public function enableNotifications(bool $enable = true): self
    {
        $this->attributes[Key::ENABLE_NOTIFICATIONS] = $enable;

        return $this;
    }

    public function disableNotifications(): self
    {
        return $this->enableNotifications(false);
    }

    public function enableFolderUpdatedNotification(bool $enable = true): self
    {
        $this->attributes[Key::NOTIFy_ON_UPDATE] = $enable;

        return $this;
    }

    public function disableFolderUpdatedNotification(): self
    {
        return $this->enableFolderUpdatedNotification(false);
    }

    public function enableNewBookmarksNotification(bool $enable = true): self
    {
        $this->attributes[Key::NOTIFY_ON_NEW_BOOKMARK] = $enable;

        return $this;
    }

    public function disableNewBookmarksNotification(): self
    {
        return $this->enableNewBookmarksNotification(false);
    }

    public function enableBookmarksRemovedNotification(bool $notify = true): self
    {
        $this->attributes[Key::NOTIFY_ON_BOOKMARK_DELETED] = $notify;

        return $this;
    }

    public function disableBookmarksRemovedNotification(): self
    {
        return $this->enableBookmarksRemovedNotification(false);
    }

    public function enableNewCollaboratorNotification(bool $enable = true): self
    {
        $this->attributes[Key::NEW_COLLABORATOR_NOTIFICATION] = $enable;

        return $this;
    }

    public function disableNewCollaboratorNotification(): self
    {
        return $this->enableNewCollaboratorNotification(false);
    }

    public function enableOnlyCollaboratorsInvitedByMeNotification(bool $enable = true): self
    {
        $this->attributes[Key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION] = $enable;

        return $this;
    }

    public function enableCollaboratorExitNotification(bool $enable = true): self
    {
        $this->attributes[Key::NOTIFY_ON_COLLABORATOR_EXIT] = $enable;

        return $this;
    }

    public function disableCollaboratorExitNotification(): self
    {
        return $this->enableCollaboratorExitNotification(false);
    }

    public function enableOnlyCollaboratorWithWritePermissionNotification(bool $enable = true): self
    {
        $this->attributes[Key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION] = $enable;

        return $this;
    }

    public function build(): FolderSettings
    {
        return new FolderSettings($this->attributes);
    }
}
