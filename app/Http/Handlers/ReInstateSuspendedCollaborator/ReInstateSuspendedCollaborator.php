<?php

declare(strict_types=1);

namespace App\Http\Handlers\ReInstateSuspendedCollaborator;

use App\Http\Handlers\SuspendCollaborator\SuspendedCollaboratorFinder;

final class ReInstateSuspendedCollaborator
{
    public function __construct(private readonly SuspendedCollaboratorFinder $finder)
    {
    }

    public function __invoke(): void
    {
        $this->finder->getRecord()->delete();
    }
}
