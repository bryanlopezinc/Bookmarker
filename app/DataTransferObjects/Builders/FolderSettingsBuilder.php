<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\Enums\CollaboratorExitNotificationMode;
use App\ValueObjects\FolderSettings;
use App\Enums\NewCollaboratorNotificationMode;
use BackedEnum;
use Illuminate\Support\Arr;

final class FolderSettingsBuilder
{
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function build(): FolderSettings
    {
        return new FolderSettings($this->attributes);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public static function new(): FolderSettingsBuilder
    {
        return new FolderSettingsBuilder();
    }

    public static function fromRequest(array $data): self
    {
        $settings = [];

        $map = [
            'enable_notifications'                    => 'notifications.enabled',
            'notify_on_new_collaborator'             => 'notifications.newCollaborator.enabled',
            'notify_on_new_collaborator_by_user'     => 'notifications.newCollaborator.mode',
            'notify_on_update'                       => 'notifications.folderUpdated.enabled',
            'notify_on_new_bookmark'                 => 'notifications.newBookmarks.enabled',
            'notify_on_bookmark_delete'              => 'notifications.bookmarksRemoved.enabled',
            'notify_on_collaborator_exit'            => 'notifications.collaboratorExit.enabled',
            'notify_on_collaborator_exit_with_write' => 'notifications.collaboratorExit.mode'
        ];

        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $map)) {
                return (new self([$key => $value]));
            }

            if ($key === 'notify_on_new_collaborator_by_user') {
                $value = $value ? NewCollaboratorNotificationMode::INVITED_BY_ME->value : NewCollaboratorNotificationMode::ALL->value;
            }

            if ($key === 'notify_on_collaborator_exit_with_write') {
                $value = $value ? CollaboratorExitNotificationMode::HAS_WRITE_PERMISSION->value : CollaboratorExitNotificationMode::ALL->value;
            }

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            Arr::set($settings, $map[$key], $value);
        }

        return new self($settings);
    }

    public function setMaxCollaboratorsLimit(int $limit): self
    {
        Arr::set($this->attributes, 'maxCollaboratorsLimit', $limit);

        return $this;
    }

    public function enableNotifications(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.enabled', $enable);

        return $this;
    }

    public function disableNotifications(): self
    {
        return $this->enableNotifications(false);
    }

    public function enableFolderUpdatedNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.folderUpdated.enabled', $enable);

        return $this;
    }

    public function disableFolderUpdatedNotification(): self
    {
        return $this->enableFolderUpdatedNotification(false);
    }

    public function enableNewBookmarksNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.newBookmarks.enabled', $enable);

        return $this;
    }

    public function disableNewBookmarksNotification(): self
    {
        return $this->enableNewBookmarksNotification(false);
    }

    public function enableBookmarksRemovedNotification(bool $notify = true): self
    {
        Arr::set($this->attributes, 'notifications.bookmarksRemoved.enabled', $notify);

        return $this;
    }

    public function disableBookmarksRemovedNotification(): self
    {
        return $this->enableBookmarksRemovedNotification(false);
    }

    public function enableNewCollaboratorNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.newCollaborator.enabled', $enable);

        return $this;
    }

    public function disableNewCollaboratorNotification(): self
    {
        return $this->enableNewCollaboratorNotification(false);
    }

    public function enableOnlyCollaboratorsInvitedByMeNotification(bool $enable = true): self
    {
        $mode = $enable ? NewCollaboratorNotificationMode::INVITED_BY_ME : NewCollaboratorNotificationMode::ALL;

        Arr::set($this->attributes, 'notifications.newCollaborator.mode', $mode->value);

        return $this;
    }

    public function enableCollaboratorExitNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.collaboratorExit.enabled', $enable);

        return $this;
    }

    public function disableCollaboratorExitNotification(): self
    {
        return $this->enableCollaboratorExitNotification(false);
    }

    public function enableOnlyCollaboratorWithWritePermissionNotification(bool $enable = true): self
    {
        $mode = $enable ? CollaboratorExitNotificationMode::HAS_WRITE_PERMISSION : CollaboratorExitNotificationMode::ALL;

        Arr::set($this->attributes, 'notifications.collaboratorExit.mode', $mode->value);

        return $this;
    }

    public function enableCannotAcceptInviteIfInviterIsNotAnActiveCollaborator(): self
    {
        $acceptInviteConstraints = Arr::pull($this->attributes, 'acceptInviteConstraints', []);

        $acceptInviteConstraints[] = 'InviterMustBeAnActiveCollaborator';

        Arr::set($this->attributes, 'acceptInviteConstraints', $acceptInviteConstraints);

        return $this;
    }

    public function enableCannotAcceptInviteIfInviterNoLongerHasRequiredPermission(): self
    {
        $acceptInviteConstraints = Arr::pull($this->attributes, 'acceptInviteConstraints', []);

        $acceptInviteConstraints[] = 'InviterMustHaveRequiredPermission';

        Arr::set($this->attributes, 'acceptInviteConstraints', $acceptInviteConstraints);

        return $this;
    }
}
