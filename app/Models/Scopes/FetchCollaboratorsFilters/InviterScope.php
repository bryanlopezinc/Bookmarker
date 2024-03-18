<?php

declare(strict_types=1);

namespace App\Models\Scopes\FetchCollaboratorsFilters;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class InviterScope
{
    public function __invoke(Builder $builder): void
    {
        $builder->withCasts(['wasInvitedBy' => 'json'])->addSelect([
            'wasInvitedBy' => User::query()
                ->selectRaw("JSON_OBJECT('id', id, 'full_name', full_name, 'profile_image_path', profile_image_path)")
                ->whereColumn('id', 'folders_collaborators.invited_by')
        ]);
    }
}
