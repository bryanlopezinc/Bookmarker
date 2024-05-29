<?php

declare(strict_types=1);

namespace App\Http\Handlers\ReInstateSuspendedCollaborator;

use App\Exceptions\HttpException;
use App\Http\Handlers\SuspendCollaborator\SuspendedCollaboratorFinder;

final class MustBeSuspendedConstraint
{
    public function __construct(private readonly SuspendedCollaboratorFinder $finder)
    {
    }

    public function __invoke(): void
    {
        if ( ! $this->finder->collaboratorIsSuspended()) {
            throw HttpException::notFound(['message' => 'CollaboratorNotSuspended']);
        }
    }
}
