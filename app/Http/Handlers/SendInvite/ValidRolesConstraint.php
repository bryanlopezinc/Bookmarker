<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\DataTransferObjects\SendInviteRequestData;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class ValidRolesConstraint implements Scope
{
    public function __construct(private readonly SendInviteRequestData $data)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $builder
            ->withCasts(['roleNames' => 'array'])
            ->addSelect([
                'roleNames' => FolderRole::query()
                    ->selectRaw('JSON_ARRAYAGG(name)')
                    ->whereColumn('folder_id', 'folders.id')
                    ->whereIn('name', $this->data->roles)
            ]);
    }

    public function __invoke(Folder $folder): void
    {
        $invalidRoles = array_diff($this->data->roles, $folder->roleNames ?? []);

        if ( ! empty($invalidRoles)) {
            throw HttpException::notFound([
                'message' => 'RoleNotFound',
                'info' => 'Roles not found'
            ]);
        }
    }
}
