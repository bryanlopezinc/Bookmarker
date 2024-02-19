<?php

declare(strict_types=1);

namespace App\Actions\AcceptFolderInvite;

use App\Exceptions\AcceptFolderInviteException;
use App\Models\Folder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use App\Models\Scopes\UserIsACollaboratorScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UniqueCollaboratorsConstraint implements HandlerInterface, Scope, FolderInviteDataAwareInterface
{
    use Concerns\HasInvitationData;

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $callback = new UserIsACollaboratorScope(
            $this->invitationData->inviteeId,
            'collaboratorIsAlreadyAMember'
        );

        $builder->tap($callback);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if ($folder->collaboratorIsAlreadyAMember) {
            throw AcceptFolderInviteException::dueToInviteeHasAlreadyAcceptedInvitation();
        }
    }
}
