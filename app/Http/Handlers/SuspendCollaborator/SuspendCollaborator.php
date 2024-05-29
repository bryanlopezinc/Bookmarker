<?php

declare(strict_types=1);

namespace App\Http\Handlers\SuspendCollaborator;

use App\Models\Folder;
use App\Models\SuspendedCollaborator;
use App\Models\User;
use Carbon\Carbon;

final class SuspendCollaborator
{
    public function __construct(
        private readonly ?int $suspensionPeriodInHours,
        private readonly SuspendedCollaboratorFinder $finder,
        private readonly User $authUser
    ) {
    }

    public function __invoke(Folder $result): void
    {
        $suspendedAt = now();

        if ($this->finder->collaboratorIsSuspended() && $this->finder->getRecord()->suspensionPeriodIsPast()) {
            $this->finder->getRecord()->update([
                'suspended_until' => self::calculateSuspensionDuration($suspendedAt, $this->suspensionPeriodInHours)
            ]);

            return;
        }

        self::suspend(
            $result->collaboratorId,
            $result,
            $suspendedAt,
            $this->suspensionPeriodInHours,
            $this->authUser
        );
    }

    public static function suspend(
        int|User $collaboratorId,
        Folder $folder,
        Carbon $suspendedAt = null,
        int $suspensionDurationInHours = null,
        int|User $suspendedBy = null,
    ): SuspendedCollaborator {
        $suspendedAt = $suspendedAt ?? now();

        $collaboratorId = $collaboratorId instanceof User ? $collaboratorId->id : $collaboratorId;
        $suspendedBy = $suspendedBy instanceof User ? $suspendedBy->id : $suspendedBy;

        return SuspendedCollaborator::query()->create([
            'folder_id'         => $folder->id,
            'collaborator_id'   => $collaboratorId,
            'suspended_by'      => $suspendedBy ?? $folder->user_id,
            'suspended_at'      => $suspendedAt ?? now(),
            'duration_in_hours' => $suspensionDurationInHours,
            'suspended_until'   => self::calculateSuspensionDuration($suspendedAt, $suspensionDurationInHours)
        ]);
    }

    private static function calculateSuspensionDuration(Carbon $suspendedAt, ?int $suspensionDurationInHours): ?Carbon
    {
        return $suspensionDurationInHours === null ? null : $suspendedAt->clone()->addHours($suspensionDurationInHours);
    }
}
