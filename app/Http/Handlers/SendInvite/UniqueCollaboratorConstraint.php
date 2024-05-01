<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\Scopes\UserIsACollaboratorScope;
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
        if( ! $this->invitee->exists) {
            return;
        }

        $builder->tap(new UserIsACollaboratorScope($this->invitee->id, 'inviteeIsAlreadyAMember'));
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
