<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\FolderSettings\SettingInterface;
use App\Enums\NewCollaboratorNotificationMode;
use App\Enums\CollaboratorExitNotificationMode;
use App\Enums\FolderActivitiesVisibility;
use App\FolderSettings\FolderSettings;
use App\FolderSettings\Settings\AcceptInviteConstraints;
use App\FolderSettings\Settings\Activities\ActivitiesVisibility;
use App\FolderSettings\Settings\Activities\LogActivities;
use App\FolderSettings\Settings\MaxBookmarksLimit;
use App\FolderSettings\Settings\MaxCollaboratorsLimit;
use App\FolderSettings\Settings\Notifications\BookmarksRemovedNotification;
use App\FolderSettings\Settings\Notifications\CollaboratorExitNotification;
use App\FolderSettings\Settings\Notifications\CollaboratorExitNotificationMode as CollaboratorExitNotificationModeSetting;
use App\FolderSettings\Settings\Notifications\FolderUpdatedNotification;
use App\FolderSettings\Settings\Notifications\NewBookmarksNotification;
use App\FolderSettings\Settings\Notifications\NewCollaboratorNotification;
use App\FolderSettings\Settings\Notifications\NewCollaboratorNotificationMode as NewCollaboratorNotificationModeSetting;
use App\FolderSettings\Settings\Notifications\Notifications;

final class FolderSettingsBuilder
{
    /**
     * @var array<SettingInterface>
     */
    private array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function build(): FolderSettings
    {
        return FolderSettings::fromKeys($this->attributes);
    }

    public static function new(array $attributes = []): FolderSettingsBuilder
    {
        return new FolderSettingsBuilder($attributes);
    }

    public function setMaxCollaboratorsLimit(int $limit): self
    {
        $this->attributes[] = new MaxCollaboratorsLimit($limit);

        return $this;
    }

    public function setMaxBookmarksLimit(int $limit): self
    {
        $this->attributes[] = new MaxBookmarksLimit($limit);

        return $this;
    }

    public function enableNotifications(bool $enable = true): self
    {
        $this->attributes[] = new Notifications($enable);

        return $this;
    }

    public function disableNotifications(): self
    {
        return $this->enableNotifications(false);
    }

    public function enableFolderUpdatedNotification(bool $enable = true): self
    {
        $this->attributes[] = new FolderUpdatedNotification($enable);

        return $this;
    }

    public function disableFolderUpdatedNotification(): self
    {
        return $this->enableFolderUpdatedNotification(false);
    }

    public function enableNewBookmarksNotification(bool $enable = true): self
    {
        $this->attributes[] = new NewBookmarksNotification($enable);

        return $this;
    }

    public function disableNewBookmarksNotification(): self
    {
        return $this->enableNewBookmarksNotification(false);
    }

    public function enableBookmarksRemovedNotification(bool $enable = true): self
    {
        $this->attributes[] = new BookmarksRemovedNotification($enable);

        return $this;
    }

    public function disableBookmarksRemovedNotification(): self
    {
        return $this->enableBookmarksRemovedNotification(false);
    }

    public function enableNewCollaboratorNotification(bool $enable = true): self
    {
        $this->attributes[] = new NewCollaboratorNotification($enable);

        return $this;
    }

    public function disableNewCollaboratorNotification(): self
    {
        return $this->enableNewCollaboratorNotification(false);
    }

    public function enableOnlyCollaboratorsInvitedByMeNotification(bool $enable = true): self
    {
        $mode = $enable ? NewCollaboratorNotificationMode::INVITED_BY_ME : NewCollaboratorNotificationMode::ALL;

        $this->attributes[] = new NewCollaboratorNotificationModeSetting($mode->value);

        return $this;
    }

    public function enableCollaboratorExitNotification(bool $enable = true): self
    {
        $this->attributes[] = new CollaboratorExitNotification($enable);

        return $this;
    }

    public function disableCollaboratorExitNotification(): self
    {
        return $this->enableCollaboratorExitNotification(false);
    }

    public function enableOnlyCollaboratorWithWritePermissionNotification(bool $enable = true): self
    {
        $mode = $enable ? CollaboratorExitNotificationMode::HAS_WRITE_PERMISSION : CollaboratorExitNotificationMode::ALL;

        $this->attributes[] = new CollaboratorExitNotificationModeSetting($mode->value);

        return $this;
    }

    public function enableCannotAcceptInviteIfInviterIsNotAnActiveCollaborator(): self
    {
        $acceptInviteConstraints = collect($this->attributes)
            ->filter(fn ($value) => $value instanceof AcceptInviteConstraints)
            ->first(default: new AcceptInviteConstraints())
            ->value()
            ->all();

        $this->attributes[] = new AcceptInviteConstraints(array_merge($acceptInviteConstraints, ['InviterMustBeAnActiveCollaborator']));

        return $this;
    }

    public function enableCannotAcceptInviteIfInviterNoLongerHasRequiredPermission(): self
    {
        $acceptInviteConstraints = collect($this->attributes)
            ->filter(fn ($value) => $value instanceof AcceptInviteConstraints)
            ->first(default: new AcceptInviteConstraints())
            ->value()
            ->all();

        $this->attributes[] = new AcceptInviteConstraints(array_merge($acceptInviteConstraints, ['InviterMustHaveRequiredPermission']));

        return $this;
    }

    public function enableActivities(bool $enable = true): self
    {
        $this->attributes[] = new LogActivities($enable);

        return $this;
    }

    public function activitiesVisibility(FolderActivitiesVisibility $visibility = FolderActivitiesVisibility::PUBLIC): self
    {
        $this->attributes[] = new ActivitiesVisibility($visibility);

        return $this;
    }
}
