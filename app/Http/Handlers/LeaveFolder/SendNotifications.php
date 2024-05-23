<?php

declare(strict_types=1);

namespace App\Http\Handlers\LeaveFolder;

use App\Models\Folder;
use App\Models\User;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Notifications\CollaboratorExitNotification as Notification;
use Illuminate\Support\Facades\Notification as Dispatcher;
use App\Enums\CollaboratorExitNotificationMode as Mode;

final class SendNotifications implements Scope
{
    private readonly User $authUser;
    private readonly CollaboratorPermissionsRepository $permissionsRepository;

    public function __construct(
        User $authUser,
        CollaboratorPermissionsRepository $permissionsRepository = null,
    ) {
        $this->authUser = $authUser;
        $this->permissionsRepository = $permissionsRepository ??= new CollaboratorPermissionsRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['settings']);
    }

    public function __invoke(Folder $folder): void
    {
        $settings = $folder->settings;

        $collaboratorPermissions = $this->permissionsRepository->all($this->authUser->id, $folder->id);

        if ($settings->notifications()->isDisabled()) {
            return;
        }

        if ($settings->collaboratorExitNotification()->isDisabled()) {
            return;
        }

        if (
            $collaboratorPermissions->isEmpty() &&
            $settings->collaboratorExitNotificationMode()->value() == Mode::HAS_WRITE_PERMISSION
        ) {
            return;
        }

        Dispatcher::send(
            new User(['id' => $folder->user_id]),
            new Notification($folder, $this->authUser)
        );
    }
}
