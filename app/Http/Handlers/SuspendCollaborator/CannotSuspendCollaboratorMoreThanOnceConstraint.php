<?php

declare(strict_types=1);

namespace App\Http\Handlers\SuspendCollaborator;

use App\Exceptions\HttpException;

final class CannotSuspendCollaboratorMoreThanOnceConstraint
{
    public function __construct(private SuspendedCollaboratorFinder $finder)
    {
    }

    public function __invoke(): void
    {
        if ( ! $this->finder->collaboratorIsSuspended()) {
            return;
        }

        if ( ! $this->finder->getRecord()->suspensionPeriodIsPast()) {
            throw HttpException::conflict(['message' => 'CollaboratorAlreadySuspended']);
        }
    }
}
