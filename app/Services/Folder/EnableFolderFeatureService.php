<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Actions\ToggleFolderFeature;
use App\Enums\Feature;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\Scopes\UserIsACollaboratorScope;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\Response;

final class EnableFolderFeatureService
{
    public function __invoke(FolderPublicId $folderId, Feature $feature, User $authUser): int
    {
        /** @var Folder $folder */
        $folder = Folder::query()
            ->with([
                'disabledFeatureTypes' => function ($query) use ($feature) {
                    $query->where('name', $feature->value);
                }
            ])
            ->select(['user_id', 'id'])
            ->tap(new UserIsACollaboratorScope($authUser->id))
            ->tap(new WherePublicIdScope($folderId))
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        if ($folder->userIsACollaborator) {
            throw HttpException::forbidden(['message' => 'PermissionDenied']);
        }

        if ( ! $folder->wasCreatedBy($authUser)) {
            throw new FolderNotFoundException();
        }

        if ($folder->disabledFeatureTypes->isEmpty()) {
            return Response::HTTP_NO_CONTENT;
        }

        (new ToggleFolderFeature())->enable($folder->id, $feature);

        return Response::HTTP_CREATED;
    }
}
