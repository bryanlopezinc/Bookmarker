<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Exceptions\HttpException;
use App\Http\Handlers\SuspendCollaborator\SuspendedCollaboratorFinder;

final class MustNotBeSuspendedConstraint
{
    public function __construct(private readonly SuspendedCollaboratorFinder $finder)
    {
    }

    public function __invoke(): void
    {
        if ( ! $this->finder->collaboratorIsSuspended()) {
            return;
        }

        if ($this->finder->getRecord()->suspensionPeriodIsPast()) {
            $suspendedCollaborator = $this->finder->getRecord();

            dispatch(static function () use ($suspendedCollaborator) {
                $suspendedCollaborator->delete();
            })->afterResponse();

            return;
        }

        throw HttpException::forbidden(['message' => 'CollaboratorSuspended']);
    }
}
