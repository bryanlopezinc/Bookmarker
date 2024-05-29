<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

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

final class InviterMustStillHaveRequiredPermissionConstraint implements Scope
{
    private PermissionConstraint $permissionConstraint;

    public function __construct(FolderInviteData $payload, PermissionConstraint $permissionConstraint = null)
    {
        $this->permissionConstraint = $permissionConstraint ??= new PermissionConstraint(new User(['id' => $payload->inviterId]), Permission::INVITE_USER);
    }

    public function apply(Builder $builder, Model $model): void
    {
        $this->permissionConstraint->apply($builder, $model);
    }

    public function __invoke(Folder $folder): void
    {
        $folderConstraints = $folder->settings->acceptInviteConstraints()->value();

        $constraint = $this->permissionConstraint;

        if ( ! $folderConstraints->inviterMustHaveRequiredPermission()) {
            return;
        }

        try {
            $constraint($folder);
        } catch (PermissionDeniedException) {
            throw HttpException::forbidden([
                'message' => 'InviterCanNoLongerSendInvites'
            ]);
        }
    }
}
