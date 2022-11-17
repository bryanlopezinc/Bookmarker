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

    public function enableNotifications(bool $enable): self
    {
        Arr::set($this->attributes, 'notifications.enabled', $enable);

        return $this;
    }

    public function notifyOnFolderUpdate(bool $notify): self
    {
        Arr::set($this->attributes, 'notifications.updated', $notify);

        return $this;
    }

    public function notifyOnNewBookmarks(bool $notify): self
    {
        Arr::set($this->attributes, 'notifications.bookmarksAdded', $notify);

        return $this;
    }

    public function notifyOnBookmarksRemoved(bool $notify): self
    {
        Arr::set($this->attributes, 'notifications.bookmarksRemoved', $notify);

        return $this;
    }

    public function notifyOnNewCollaborator(bool $notify): self
    {
        Arr::set($this->attributes, 'notifications.newCollaborator.notify', $notify);

        return $this;
    }

    public function notifyOnNewCollaboratorOnlyInvitedByMe(bool $notify): self
    {
        Arr::set($this->attributes, 'notifications.newCollaborator.onlyCollaboratorsInvitedByMe', $notify);

        return $this;
    }

    public function notifyOnCollaboratorExit(bool $notify): self
    {
        Arr::set($this->attributes, 'notifications.collaboratorExit.notify', $notify);

        return $this;
    }

    public function notifyOnCollaboratorExitOnlyWhenHasWritePermission(bool $notify): self
    {
        Arr::set($this->attributes, 'notifications.collaboratorExit.onlyWhenCollaboratorHasWritePermission', $notify);

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
