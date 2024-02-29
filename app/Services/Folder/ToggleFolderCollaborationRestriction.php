<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Http\Requests\UpdateCollaboratorActionRequest as Request;
use App\Models\Folder;
use App\Models\FolderDisabledFeature as Model;
use App\Enums\Permission;
use Illuminate\Support\Collection;

final class ToggleFolderCollaborationRestriction
{
    public function fromRequest(Request $request): void
    {
        $folderId = $request->integer('folder_id');

        $folder = Folder::query()->find($folderId, ['user_id']);

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $disabledFeatures = Model::query() //@phpstan-ignore-line
            ->where('folder_id', $folderId)
            ->get(['feature'])
            ->pluck('feature');

        $this->mapFeatures($request)
            ->tap(function (Collection $features) {
                $features->filter()->whenNotEmpty(function (Collection $features) {
                    Model::whereIn('feature', $features->keys())->delete();
                });
            })
            ->filter(fn (bool $enabled) => !$enabled)
            ->forget($disabledFeatures)
            ->whenNotEmpty(function (Collection $features) use ($folderId) {
                $disabledFeatures = $features->map(fn ($enabled, string $feature) => [
                    'folder_id' => $folderId,
                    'feature'   => $feature
                ]);

                Model::insert($disabledFeatures->all());
            });
    }

    private function mapFeatures(Request $request): Collection
    {
        $features = [
            Permission::ADD_BOOKMARKS->value    => $request->validated('addBookmarks', null),
            Permission::DELETE_BOOKMARKS->value => $request->validated('removeBookmarks', null),
            Permission::INVITE_USER->value      => $request->validated('inviteUsers', null),
            Permission::UPDATE_FOLDER->value    => $request->validated('updateFolder', null)
        ];

        return collect($features)
            ->reject(fn ($value) => $value === null)
            ->map(fn ($value) => boolval($value));
    }

    public function update(int $folderId, Permission $permission, bool $enable): void
    {
        if ($enable) {
            Model::where('folder_id', $folderId)->delete();
        } else {
            Model::query()->create(['folder_id' => $folderId, 'feature' => $permission->value]);
        }
    }
}
