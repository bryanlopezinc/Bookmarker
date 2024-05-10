<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Http\Handlers\SuspendCollaborator\SuspendedCollaboratorFinder;

final class RemoveCollaboratorFromSuspendedCollaboratorsList
{
    public function __construct(private readonly SuspendedCollaboratorFinder $finder)
    {
    }

    public function __invoke(): void
    {
        if ( ! $this->finder->collaboratorIsSuspended()) {
            return;
        }

        $suspendedCollaborator = $this->finder->getRecord();

        dispatch(static function () use ($suspendedCollaborator) {
            $suspendedCollaborator->delete();
        })->afterResponse();
    }
}
