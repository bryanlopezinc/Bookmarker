<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\FolderCollaborator;
use App\Exceptions\FolderNotFoundException;
use App\Models\FolderCollaboratorPermission;
use App\Models\FolderPermission;
use App\Models\User;
use App\PaginationData;
use App\UAC;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\Paginator;

final class FetchFolderCollaboratorsService
{
    public function __construct(private FetchFolderService $folderRepository)
    {
    }

    /**
     * @return Paginator<FolderCollaborator>
     */
    public function get(int $folderID, PaginationData $pagination, UAC $filter = null): Paginator
    {
        $folder = $this->folderRepository->find($folderID, ['id', 'user_id']);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        return $this->collaborators($folderID, $pagination, $filter);
    }

    /**
     * @return Paginator<FolderCollaborator>
     */
    private function collaborators(int $folderID, PaginationData $pagination, UAC $filter = null): Paginator
    {
        $model = new FolderCollaboratorPermission();
        $um = new User(); // user model
        $fpm = new FolderPermission(); // folder permission model

        $query = User::query()
            ->select([$um->getQualifiedKeyName(), 'first_name', 'last_name', new Expression('JSON_ARRAYAGG(name) as permissions')])
            ->join($model->getTable(), $model->qualifyColumn('user_id'), '=', $um->getQualifiedKeyName())
            ->join($fpm->getTable(), $model->qualifyColumn('permission_id'), '=', $fpm->getQualifiedKeyName());

        $query->when(!is_null($filter) && !$filter?->hasAllPermissions(), function ($query) use ($filter) {
            $values = collect($filter->permissions)
                ->map(fn (string $permission) => "'{$permission}'")
                ->implode(',');

            if ($filter->hasOnlyReadPermission()) {
                $query->havingRaw("JSON_ARRAY({$values}) = permissions");
            } else {
                $query->havingRaw("JSON_CONTAINS(permissions, JSON_ARRAY({$values}))");
            }
        });

        $query->when($filter?->hasAllPermissions(), function ($query) use ($filter) {
            $values = collect($filter->all()->permissions)
                ->map(fn (string $permission) => "'{$permission}'")
                ->implode(',');

            $query->havingRaw("JSON_ARRAY({$values}) = permissions");
        });

        /** @var Paginator */
        $result = $query->groupBy(['users.id', 'first_name', 'last_name'])
            ->where('folder_id', $folderID)
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $result->setCollection(
            $result->map(
                $this->createCollaboratorFn()
            )
        );
    }

    private function createCollaboratorFn(): \Closure
    {
        return function (User $model) {
            $model->mergeCasts(['permissions' => 'array']);

            return new FolderCollaborator(
                $model,
                new UAC($model->permissions)
            );
        };
    }
}
