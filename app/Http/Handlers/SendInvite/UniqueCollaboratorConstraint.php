<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderCollaborator;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UniqueCollaboratorConstraint implements Scope
{
    public function __construct(private readonly User $invitee)
    {
    }

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->addSelect([
            'inviteeIsAlreadyAMember' => FolderCollaborator::select('id')
                ->whereColumn('folder_id', 'folders.id')
                ->where('folders_collaborators.collaborator_id', $this->invitee->id)
        ]);
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->inviteeIsAlreadyAMember) {
            throw HttpException::conflict([
                'message' => 'UserAlreadyACollaborator',
                'info' => 'Request could not be completed because the user is already a collaborator.'
            ]);
        }
    }
}
