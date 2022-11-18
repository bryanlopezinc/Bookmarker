<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\DataTransferObjects\FolderSettings;
use Illuminate\Support\Arr;

final class FolderSettingsBuilder extends Builder
{
    public function __construct(array $attributes = [])
    {
        $attributes = empty($attributes) ? FolderSettings::default()->toArray() : $attributes;

        parent::__construct($attributes);
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
        Arr::set($this->attributes, 'notifications.updated', $enable);

        return $this;
    }

    public function disableFolderUpdatedNotification(): self
    {
        return $this->enableFolderUpdatedNotification(false);
    }

    public function enableNewBookmarksNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.bookmarksAdded', $enable);

        return $this;
    }

    public function disableNewBookmarksNotification(): self
    {
        return $this->enableNewBookmarksNotification(false);
    }

    public function enableBookmarksRemovedNotification(bool $notify = true): self
    {
        Arr::set($this->attributes, 'notifications.bookmarksRemoved', $notify);

        return $this;
    }

    public function disableBookmarksRemovedNotification(): self
    {
        return $this->enableBookmarksRemovedNotification(false);
    }

    public function enableNewCollaboratorNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.newCollaborator.notify', $enable);

        return $this;
    }

    public function disableNewCollaboratorNotification(): self
    {
        return $this->enableNewCollaboratorNotification(false);
    }

    public function enableOnlyCollaboratorsInvitedByMeNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.newCollaborator.onlyCollaboratorsInvitedByMe', $enable);

        return $this;
    }

    public function enableCollaboratorExitNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.collaboratorExit.notify', $enable);

        return $this;
    }

    public function disableCollaboratorExitNotification(): self
    {
        return $this->enableCollaboratorExitNotification(false);
    }

    public function enableOnlyCollaboratorWithWritePermissionNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.collaboratorExit.onlyWhenCollaboratorHasWritePermission', $enable);

        return $this;
    }

    public function version(string $version): self
    {
        $this->attributes['version'] = $version;

        return $this;
    }

    public function build(): FolderSettings
    {
        return new FolderSettings($this->attributes);
    }
}
