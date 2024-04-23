<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRole;

use App\Contracts\IdGeneratorInterface;
use App\DataTransferObjects\CreateFolderRoleData;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderRolePermission;
use App\UAC;
use Illuminate\Support\Facades\DB;

final class CreateFolderRole
{
    private readonly CreateFolderRoleData $data;
    private readonly IdGeneratorInterface $generator;

    public function __construct(CreateFolderRoleData $data, IdGeneratorInterface $idGeneratorInterface = null)
    {
        $this->data = $data;
        $this->generator = $idGeneratorInterface ??= app(IdGeneratorInterface::class);
    }

    public function __invoke(Folder $folder): void
    {
        $permissions = UAC::fromRequest($this->data->permissions);

        $role = $folder->roles()->create([
            'name'      => $this->data->name,
            'public_id' => $this->generator->generate()
        ]);

        /** @var \Illuminate\Database\Eloquent\Builder */
        $query = FolderPermission::query()
            ->select([DB::raw($role->id), 'id'])
            ->whereIn('name', $permissions->toArray());

        FolderRolePermission::insertUsing(['role_id', 'permission_id'], $query);
    }
}
