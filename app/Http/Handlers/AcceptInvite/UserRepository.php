<?php

declare(strict_types=1);

namespace App\Http\Handlers\AcceptInvite;

use App\DataTransferObjects\FolderInviteData;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UserRepository implements Scope
{
    private readonly FolderInviteData $data;
    private User $inviter;
    private User $invitee;

    public function __construct(FolderInviteData $data)
    {
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model)
    {
        $invitationData = $this->data;

        $expression = "JSON_OBJECT('id', id, 'full_name', full_name, 'public_id', public_id, 'profile_image_path', profile_image_path)";

        $builder->withCasts(['inviter' => 'json', 'invitee' => 'json'])
            ->addSelect(['inviter' => User::selectRaw($expression)->where('id', $invitationData->inviterId)])
            ->addSelect(['invitee' => User::selectRaw($expression)->where('id', $invitationData->inviteeId)]);
    }

    public function inviter(): User
    {
        return $this->inviter;
    }

    public function invitee(): User
    {
        return $this->invitee;
    }

    public function __invoke(Folder $folder): void
    {
        $this->invitee = $this->map($folder->invitee);

        $this->inviter = $this->map($folder->inviter);
    }

    private function map(?array $attributes): User
    {
        $attributes = $attributes ??= [];

        $instance = new User($attributes);

        $instance->exists = $attributes !== [];

        return $instance;
    }
}
