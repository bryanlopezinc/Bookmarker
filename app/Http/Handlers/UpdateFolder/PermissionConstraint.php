<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\Permission;
use App\Http\Handlers\Constraints\PermissionConstraint as Constraint;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class PermissionConstraint implements Scope
{
    private readonly Constraint $constraint;

    public function __construct(UpdateFolderRequestData $data)
    {
        $this->constraint = new Constraint($data->authUser, $this->getRequiredPermissionsForUpdate($data));
    }

    private function getRequiredPermissionsForUpdate(UpdateFolderRequestData $request): array
    {
        $permissions = new Collection();

        return $permissions
            ->when($request->isUpdatingName, function (Collection $permissions) {
                return $permissions->add(Permission::UPDATE_FOLDER_NAME);
            })
            ->when($request->isUpdatingDescription, function (Collection $permissions) {
                return $permissions->add(Permission::UPDATE_FOLDER_DESCRIPTION);
            })
            ->when($request->isUpdatingIcon, function (Collection $permissions) {
                return $permissions->add(Permission::UPDATE_FOLDER_ICON);
            })
            ->all();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $this->constraint->apply($builder, $model);
    }

    public function __invoke(Folder $folder): void
    {
        $this->constraint->__invoke($folder);
    }
}
