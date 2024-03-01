<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\BannedCollaborator;
use App\Models\Folder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CannotSendInviteToABannedCollaboratorConstraint implements FolderRequestHandlerInterface, Scope, InviteeAwareInterface
{
    use Concerns\HasInviteeData;

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->addSelect([
            'inviteeIsBanned' => BannedCollaborator::query()
                ->select('id')
                ->whereColumn('folder_id', 'folders.id')
                ->where('user_id', $this->invitee->id)
        ]);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if (!is_null($folder->inviteeIsBanned)) {
            throw HttpException::forbidden([
                'message' => 'UserBanned',
                'info' => 'Request could not be completed because the user has been banned from the folder.'
            ]);
        }
    }
}
