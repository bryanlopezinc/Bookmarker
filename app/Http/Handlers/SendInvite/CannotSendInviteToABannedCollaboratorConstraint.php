<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\BannedCollaborator;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CannotSendInviteToABannedCollaboratorConstraint implements FolderRequestHandlerInterface, Scope
{
    public function __construct(private readonly User $invitee)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $builder
            ->withCasts(['inviteeIsBanned' => 'boolean'])
            ->addSelect([
                'inviteeIsBanned' => BannedCollaborator::query()
                    ->selectRaw('1')
                    ->whereColumn('folder_id', 'folders.id')
                    ->where('user_id', $this->invitee->id)
            ]);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if ($folder->inviteeIsBanned) {
            throw HttpException::forbidden([
                'message' => 'UserBanned',
                'info' => 'Request could not be completed because the user has been banned from the folder.'
            ]);
        }
    }
}
