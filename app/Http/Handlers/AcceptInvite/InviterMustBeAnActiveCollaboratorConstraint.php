<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\FolderInviteData;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Http\Handlers\Constraints\MustBeACollaboratorConstraint;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class InviterMustBeAnActiveCollaboratorConstraint implements FolderRequestHandlerInterface, Scope, InvitationDataAwareInterface
{
    private MustBeACollaboratorConstraint $mustBeACollaboratorConstraint;

    public function setInvitationData(FolderInviteData $payload): void
    {
        $inviter = new User(['id' => $payload->inviterId]);

        $this->mustBeACollaboratorConstraint = new MustBeACollaboratorConstraint($inviter);
    }

    public function apply(Builder $builder, Model $model): void
    {
        $this->mustBeACollaboratorConstraint->apply($builder, $model);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $folderConstraints = $folder->settings->acceptInviteConstraints;

        if ( ! $folderConstraints->inviterMustBeAnActiveCollaborator()) {
            return;
        }

        try {
            $this->mustBeACollaboratorConstraint->handle($folder);
        } catch (FolderNotFoundException) {
            throw HttpException::forbidden([
                'message' => 'InviterIsNotAnActiveCollaborator'
            ]);
        }
    }
}
