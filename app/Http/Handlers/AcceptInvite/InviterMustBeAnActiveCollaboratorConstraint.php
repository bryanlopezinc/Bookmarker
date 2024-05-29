<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\DataTransferObjects\FolderInviteData;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Http\Handlers\Constraints\MustBeACollaboratorConstraint;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class InviterMustBeAnActiveCollaboratorConstraint implements Scope
{
    private MustBeACollaboratorConstraint $mustBeACollaboratorConstraint;

    public function __construct(FolderInviteData $payload)
    {
        $inviter = new User(['id' => $payload->inviterId]);

        $inviter->exists = true;

        $this->mustBeACollaboratorConstraint = new MustBeACollaboratorConstraint($inviter);
    }

    public function apply(Builder $builder, Model $model): void
    {
        $this->mustBeACollaboratorConstraint->apply($builder, $model);
    }

    public function __invoke(Folder $folder): void
    {
        $folderConstraints = $folder->settings->acceptInviteConstraints()->value();

        $constraint = $this->mustBeACollaboratorConstraint;

        if ( ! $folderConstraints->inviterMustBeAnActiveCollaborator()) {
            return;
        }

        try {
            $constraint($folder);
        } catch (FolderNotFoundException) {
            throw HttpException::forbidden([
                'message' => 'InviterIsNotAnActiveCollaborator'
            ]);
        }
    }
}
