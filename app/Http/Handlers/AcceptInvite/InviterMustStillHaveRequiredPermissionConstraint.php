<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\FolderInviteData;
use App\Enums\Permission;
use App\Exceptions\HttpException;
use App\Exceptions\PermissionDeniedException;
use App\Http\Handlers\Constraints\PermissionConstraint;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class InviterMustStillHaveRequiredPermissionConstraint implements FolderRequestHandlerInterface, Scope, InvitationDataAwareInterface
{
    private PermissionConstraint $permissionConstraint;

    public function setInvitationData(FolderInviteData $payload): void
    {
        $inviter = new User(['id' => $payload->inviterId]);

        $this->permissionConstraint = new PermissionConstraint($inviter, Permission::INVITE_USER);
    }

    public function apply(Builder $builder, Model $model): void
    {
        $this->permissionConstraint->apply($builder, $model);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $folderConstraints = $folder->settings->acceptInviteConstraints;

        if ( ! $folderConstraints->inviterMustHaveRequiredPermission()) {
            return;
        }

        try {
            $this->permissionConstraint->handle($folder);
        } catch (PermissionDeniedException) {
            throw HttpException::forbidden([
                'message' => 'InviterCanNoLongerSendInvites'
            ]);
        }
    }
}
