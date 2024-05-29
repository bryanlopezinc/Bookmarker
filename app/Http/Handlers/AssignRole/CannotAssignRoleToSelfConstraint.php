<?php

declare(strict_types=1);

namespace App\Http\Handlers\AssignRole;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\User;

final class CannotAssignRoleToSelfConstraint
{
    public function __construct(private readonly User $authUser)
    {
    }

    public function __invoke(Folder $result): void
    {
        if ($result->collaboratorId === $this->authUser->id) {
            throw HttpException::forbidden([
                'message' => 'CannotAssignRoleToSelf',
                'info'    => ''
            ]);
        }
    }
}
