<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\DataTransferObjects\FolderInviteData;
use App\Exceptions\AcceptFolderInviteException;
use App\Models\Folder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use App\Models\Scopes\UserIsACollaboratorScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class HasNotAlreadyAcceptedInviteValidator implements Scope
{
    public function __construct(private readonly FolderInviteData $invitationData)
    {
    }

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $callback = new UserIsACollaboratorScope(
            $this->invitationData->inviteeId,
            'collaboratorIsAlreadyAMember'
        );

        $builder->tap($callback);
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->collaboratorIsAlreadyAMember) {
            throw AcceptFolderInviteException::inviteeHasAlreadyAcceptedInvitation();
        }
    }
}
