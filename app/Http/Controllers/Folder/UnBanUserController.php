<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Models\BannedCollaborator;
use App\Rules\ResourceIdRule;
use App\Services\Folder\FetchFolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UnBanUserController
{
    public function __invoke(Request $request, FetchFolderService $service): JsonResponse
    {
        $request->validate(['folder_id' => ['required', new ResourceIdRule()]]);

        $request->validate(['user_id' => ['required', new ResourceIdRule()]]);

        $folder = $service->find($request->integer('folder_id'), ['user_id']);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $affectedRows = BannedCollaborator::query()
            ->where('user_id', $request->integer('user_id'))
            ->where('folder_id', $request->integer('folder_id'))
            ->delete();

        if ($affectedRows === 0) {
            throw new UserNotFoundException();
        }

        return response()->json();
    }
}
