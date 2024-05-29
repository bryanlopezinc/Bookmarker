<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Exceptions\RoleNotFoundException;
use App\Models\Folder;

final class RoleExistsConstraint
{
    public function __construct(private readonly string $roleIdName = 'roleId')
    {
    }

    public function __invoke(Folder $folder): void
    {
        if ( ! $folder->{$this->roleIdName}) {
            throw new RoleNotFoundException();
        }
    }
}
