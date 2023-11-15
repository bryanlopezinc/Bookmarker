<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Http\Resources\BannedCollaboratorResource;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\Models\BannedCollaborator;
use App\Models\User;
use App\PaginationData;
use App\Rules\ResourceIdRule;
use App\Services\Folder\FetchFolderService;
use Illuminate\Http\Request;

final class FetchBannedCollaboratorsController
{
    public function __invoke(Request $request, FetchFolderService $service): ResourceCollection
    {
        $request->validate(['folder_id' => ['required', new ResourceIdRule()]]);

        $request->validate(PaginationData::new()->asValidationRules());

        $folder = $service->find($request->integer('folder_id'), ['user_id']);

        $pagination = PaginationData::fromRequest($request);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $bannedUsers = User::query()
            ->select(['id', 'first_name', 'last_name'])
            ->whereIn('id', BannedCollaborator::select('user_id')->where('folder_id', $request->integer('folder_id')))
            ->simplePaginate($pagination->perPage(), [], page: $pagination->page());

        return new ResourceCollection($bannedUsers, BannedCollaboratorResource::class);
    }
}
