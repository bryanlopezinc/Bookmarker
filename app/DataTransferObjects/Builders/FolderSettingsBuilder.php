<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\Enums\CollaboratorExitNotificationMode;
use App\ValueObjects\FolderSettings;
use App\Enums\NewCollaboratorNotificationMode;
use Illuminate\Support\Arr;

final class FolderSettingsBuilder
{
    private array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function build(): FolderSettings
    {
        return new FolderSettings($this->attributes);
    }

    public static function new(array $attributes = []): FolderSettingsBuilder
    {
        return new FolderSettingsBuilder($attributes);
    }

    public function setMaxCollaboratorsLimit(int $limit): self
    {
        Arr::set($this->attributes, 'max_collaborators_limit', $limit);

        return $this;
    }

    public function setMaxBookmarksLimit(int $limit): self
    {
        Arr::set($this->attributes, 'max_bookmarks_limit', $limit);

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
        Arr::set($this->attributes, 'notifications.folder_updated.enabled', $enable);

        return $this;
    }

    public function disableFolderUpdatedNotification(): self
    {
        return $this->enableFolderUpdatedNotification(false);
    }

    public function enableNewBookmarksNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.new_bookmarks.enabled', $enable);

        return $this;
    }

    public function disableNewBookmarksNotification(): self
    {
        return $this->enableNewBookmarksNotification(false);
    }

    public function enableBookmarksRemovedNotification(bool $notify = true): self
    {
        Arr::set($this->attributes, 'notifications.bookmarks_removed.enabled', $notify);

        return $this;
    }

    public function disableBookmarksRemovedNotification(): self
    {
        return $this->enableBookmarksRemovedNotification(false);
    }

    public function enableNewCollaboratorNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.new_collaborator.enabled', $enable);

        return $this;
    }

    public function disableNewCollaboratorNotification(): self
    {
        return $this->enableNewCollaboratorNotification(false);
    }

    public function enableOnlyCollaboratorsInvitedByMeNotification(bool $enable = true): self
    {
        $mode = $enable ? NewCollaboratorNotificationMode::INVITED_BY_ME : NewCollaboratorNotificationMode::ALL;

        Arr::set($this->attributes, 'notifications.new_collaborator.mode', $mode->value);

        return $this;
    }

    public function enableCollaboratorExitNotification(bool $enable = true): self
    {
        Arr::set($this->attributes, 'notifications.collaborator_exit.enabled', $enable);

        return $this;
    }

    public function disableCollaboratorExitNotification(): self
    {
        return $this->enableCollaboratorExitNotification(false);
    }

    public function enableOnlyCollaboratorWithWritePermissionNotification(bool $enable = true): self
    {
        $mode = $enable ? CollaboratorExitNotificationMode::HAS_WRITE_PERMISSION : CollaboratorExitNotificationMode::ALL;

        Arr::set($this->attributes, 'notifications.collaborator_exit.mode', $mode->value);

        return $this;
    }

    public function enableCannotAcceptInviteIfInviterIsNotAnActiveCollaborator(): self
    {
        $acceptInviteConstraints = Arr::pull($this->attributes, 'accept_invite_constraints', []);

        $acceptInviteConstraints[] = 'InviterMustBeAnActiveCollaborator';

        Arr::set($this->attributes, 'accept_invite_constraints', $acceptInviteConstraints);

        return $this;
    }

    public function enableCannotAcceptInviteIfInviterNoLongerHasRequiredPermission(): self
    {
        $acceptInviteConstraints = Arr::pull($this->attributes, 'accept_invite_constraints', []);

        $acceptInviteConstraints[] = 'InviterMustHaveRequiredPermission';

        Arr::set($this->attributes, 'accept_invite_constraints', $acceptInviteConstraints);

        return $this;
    }
}
