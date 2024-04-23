<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\DataTransferObjects\FolderInviteData;
use App\Exceptions\AcceptFolderInviteException;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

final class InviterAndInviteeExistsValidator implements Scope
{
    public function __construct(private readonly FolderInviteData $invitationData)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $invitationData = $this->invitationData;

        $expression = DB::raw("JSON_OBJECT('id', id, 'full_name', full_name)");

        $builder->withCasts(['inviter' => 'json', 'invitee' => 'json'])
            ->addSelect(['inviter' => User::select($expression)->where('id', $invitationData->inviterId)])
            ->addSelect(['invitee' => User::select($expression)->where('id', $invitationData->inviteeId)]);
    }

    public function __invoke(Folder $folder): void
    {
        if (is_null($folder->invitee)) {
            throw AcceptFolderInviteException::inviteeAccountNoLongerExists();
        }

        if (is_null($folder->inviter)) {
            throw AcceptFolderInviteException::inviterAccountNoLongerExists();
        }
    }
}
