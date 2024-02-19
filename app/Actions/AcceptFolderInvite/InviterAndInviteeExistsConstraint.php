<?php

declare(strict_types=1);

namespace App\Actions\AcceptFolderInvite;

use App\Exceptions\AcceptFolderInviteException;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

final class InviterAndInviteeExistsConstraint implements HandlerInterface, Scope, FolderInviteDataAwareInterface
{
    use Concerns\HasInvitationData;

    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $invitationData = $this->invitationData;

        $expression = DB::raw("JSON_OBJECT('id', id, 'full_name', full_name)");

        $builder->withCasts(['inviter' => 'json', 'invitee' => 'json']) //@phpstan-ignore-line
            ->addSelect(['inviter' => User::select($expression)->where('id', $invitationData->inviterId)])
            ->addSelect(['invitee' => User::select($expression)->where('id', $invitationData->inviteeId)]);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if (is_null($folder->invitee)) {
            throw AcceptFolderInviteException::dueToInviteeAccountNoLongerExists();
        }

        if (is_null($folder->inviter)) {
            throw AcceptFolderInviteException::dueToInviterAccountNoLongerExists();
        }
    }
}
