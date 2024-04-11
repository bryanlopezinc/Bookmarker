<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Exceptions\UserNotFoundException;
use App\Models\Folder;
use App\Models\MutedCollaborator;
use App\Models\Scopes\UserIsACollaboratorScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class MuteCollaboratorService
{
    private readonly Carbon $currentDateTime;

    public function __construct()
    {
        $this->currentDateTime = now();
    }

    public function __invoke(int $folderId, int $collaboratorId, int $authUserId, int $muteDurationInHours): void
    {
        $muteDurationInHours = $muteDurationInHours === 0 ? null : $muteDurationInHours;

        $folder = Folder::onlyAttributes(['user_id'])
            ->withCasts(['mutedCollaborator' => 'array'])
            ->tap(new UserIsACollaboratorScope($collaboratorId))
            ->addSelect([
                'mutedCollaborator' => MutedCollaborator::query()
                    ->select(DB::raw("JSON_OBJECT('id', id, 'muted_until', muted_until)"))
                    ->whereColumn('folder_id', 'folders.id')
                    ->where('user_id', $collaboratorId)
            ])
            ->whereKey($folderId)
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        if ($folder->user_id === $collaboratorId) {
            throw HttpException::forbidden(['message' => 'CannotMuteSelf']);
        }

        if ( ! $folder->userIsACollaborator) {
            throw new UserNotFoundException();
        }

        if ($mutedCollaborator = $folder->mutedCollaborator) {
            $this->ensureCollaboratorIsNotAlreadyMuted($mutedCollaborator);
        }

        $this->mute($folderId, $collaboratorId, $authUserId, muteDurationInHours: $muteDurationInHours);
    }

    private function ensureCollaboratorIsNotAlreadyMuted(array $mutedCollaboratorRecord): void
    {
        $exception = HttpException::conflict(['message' => 'CollaboratorAlreadyMuted']);

        if (is_null($mutedCollaboratorRecord['muted_until'])) {
            throw $exception;
        }

        if (Carbon::parse($mutedCollaboratorRecord['muted_until'])->isAfter($this->currentDateTime)) {
            throw $exception;
        }

        MutedCollaborator::query()->whereKey($mutedCollaboratorRecord['id'])->delete();
    }

    public function mute(
        int $folderId,
        int|array $collaborators,
        int $mutedBy,
        Carbon $mutedAt = null,
        int $muteDurationInHours = null
    ): void {
        $mutedAt = $mutedAt ?: $this->currentDateTime;

        $muteDurationInHours =  $muteDurationInHours ? $mutedAt->clone()->addHours($muteDurationInHours) : null;

        $records = array_map(
            array: (array) $collaborators,
            callback: function (int $collaboratorId) use ($folderId, $mutedBy, $mutedAt, $muteDurationInHours) {
                return [
                    'folder_id'   => $folderId,
                    'user_id'     => $collaboratorId,
                    'muted_by'    => $mutedBy,
                    'muted_at'    => $mutedAt,
                    'muted_until' => $muteDurationInHours
                ];
            }
        );

        MutedCollaborator::insert($records);
    }
}
