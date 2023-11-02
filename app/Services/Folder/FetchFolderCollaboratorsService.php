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
use App\Http\Requests\FetchFolderCollaboratorsRequest as Request;

final class FetchFolderCollaboratorsService
{
    public function __construct(private FetchFolderService $folderRepository)
    {
    }

    /**
     * @return Paginator<FolderCollaborator>
     */
    public function fromRequest(Request $request): Paginator
    {
        $folder = $this->folderRepository->find($request->integer('folder_id'), ['id', 'user_id']);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        return $this->collaborators(
            $folder->id,
            PaginationData::fromRequest($request),
            $request->getPermissions(),
            $request->validated('name')
        );
    }

    /**
     * @return Paginator<FolderCollaborator>
     */
    private function collaborators(int $folderID, PaginationData $pagination, ?UAC $permissions = null, ?string $collaboratorName = null): Paginator
    {
        $model = new FolderCollaboratorPermission();
        $um = new User(); // user model
        $fpm = new FolderPermission(); // folder permission model

        $query = User::query()
            ->select([$um->getQualifiedKeyName(), 'full_name', new Expression('JSON_ARRAYAGG(name) as permissions')])
            ->join($model->getTable(), $model->qualifyColumn('user_id'), '=', $um->getQualifiedKeyName())
            ->join($fpm->getTable(), $model->qualifyColumn('permission_id'), '=', $fpm->getQualifiedKeyName());

        $query->when(!is_null($permissions) && !$permissions?->hasAllPermissions(), function ($query) use ($permissions) {
            $values = collect($permissions->permissions)
                ->map(fn (string $permission) => "'{$permission}'")
                ->implode(',');

            if ($permissions->hasOnlyReadPermission()) {
                $query->havingRaw("JSON_ARRAY({$values}) = permissions");
            } else {
                $query->havingRaw("JSON_CONTAINS(permissions, JSON_ARRAY({$values}))");
            }
        });

        $query->when($permissions?->hasAllPermissions(), function ($query) use ($permissions) {
            $values = collect($permissions->all()->permissions)
                ->map(fn (string $permission) => "'{$permission}'")
                ->implode(',');

            $query->havingRaw("JSON_ARRAY({$values}) = permissions");
        });

        $query->when($collaboratorName, function ($query) use ($collaboratorName) {
            $query->where('full_name', 'like', "{$collaboratorName}%");
        });

        /** @var Paginator */
        $result = $query->groupBy(['users.id', 'full_name'])
            ->where('folder_id', $folderID)
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $result->setCollection(
            $result->map($this->createCollaboratorFn())
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
