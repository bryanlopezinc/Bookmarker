<?php

declare(strict_types=1);

namespace App\Http\Handlers\SuspendCollaborator;

use App\Exceptions\UserNotFoundException;
use App\Models\Folder;

final class CollaboratorExistsConstraint
{
    public function __invoke(Folder $result): void
    {
        if ($result->collaboratorId === null) {
            throw new UserNotFoundException();
        }
    }
}
