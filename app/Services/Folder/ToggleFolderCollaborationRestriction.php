<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Http\Requests\UpdateCollaboratorActionRequest as Request;
use App\Models\Folder;
use App\Models\FolderDisabledAction as Model;
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

        $disabledActions = Model::query() //@phpstan-ignore-line
            ->where('folder_id', $folderId)
            ->get(['action'])
            ->pluck('action');

        $this->mapActions($request)
            ->tap(function (Collection $actions) {
                $actions->filter()->whenNotEmpty(function (Collection $actions) {
                    Model::whereIn('action', $actions->keys())->delete();
                });
            })
            ->filter(fn (bool $enabled) => !$enabled)
            ->forget($disabledActions)
            ->whenNotEmpty(function (Collection $actions) use ($folderId) {
                $disabledActions = $actions->map(fn ($enabled, string $action) => [
                    'folder_id' => $folderId,
                    'action'    => $action
                ]);

                Model::insert($disabledActions->all());
            });
    }

    private function mapActions(Request $request): Collection
    {
        $actions = [
            Permission::ADD_BOOKMARKS->value    => $request->validated('addBookmarks', null),
            Permission::DELETE_BOOKMARKS->value => $request->validated('removeBookmarks', null),
            Permission::INVITE_USER->value      => $request->validated('inviteUser', null),
            Permission::UPDATE_FOLDER->value    => $request->validated('updateFolder', null)
        ];

        return collect($actions)
            ->reject(fn ($value) => $value === null)
            ->map(fn ($value) => boolval($value));
    }

    public function update(int $folderId, Permission $permission, bool $enable): void
    {
        if ($enable) {
            Model::where('folder_id', $folderId)->delete();
        } else {
            Model::query()->create(['folder_id' => $folderId, 'action' => $permission->value]);
        }
    }
}
