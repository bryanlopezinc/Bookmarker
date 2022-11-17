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

    public function notifyOnNewCollaborator(bool $notify): self
    {
        Arr::set($this->attributes, 'notifications.newCollaborator.notify', $notify);

        return $this;
    }

    public function notifyOnNewCollaboratorOnlyWhenInvitedByMe(bool $notify): self
    {
        Arr::set($this->attributes, 'notifications.newCollaborator.onlyCollaboratorsInvitedByMe', $notify);

        return $this;
    }

    public function notifyOnCollaboratorExit(bool $notify): self
    {
        Arr::set($this->attributes, 'notifications.collaboratorExit.notify', $notify);

        return $this;
    }

    public function notifyOnCollaboratorExitOnlyWhenHasPermission(bool $notify): self
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
