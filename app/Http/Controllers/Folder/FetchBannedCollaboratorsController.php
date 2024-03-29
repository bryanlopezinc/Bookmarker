<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Http\Resources\BannedCollaboratorResource;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\Models\BannedCollaborator;
use App\Models\Folder;
use App\Models\User;
use App\PaginationData;
use Illuminate\Http\Request;

final class FetchBannedCollaboratorsController
{
    public function __invoke(Request $request): ResourceCollection
    {
        $request->validate([
            'name' => ['sometimes', 'filled', 'string', 'max:10']
        ]);

        $request->validate(PaginationData::new()->asValidationRules());

        $folder = Folder::query()->find($request->route('folder_id'), ['user_id']);

        $pagination = PaginationData::fromRequest($request);

        FolderNotFoundException::throwIf(!$folder);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $bannedUsers = User::query()
            ->select(['id', 'full_name', 'profile_image_path'])
            ->when($request->has('name'), function ($query) use ($request) {
                $query->where('full_name', 'like', "{$request->input('name')}%");
            })
            ->whereIn('id', BannedCollaborator::select('user_id')->where('folder_id', $request->route('folder_id')))
            ->simplePaginate($pagination->perPage(), [], page: $pagination->page());

        return new ResourceCollection($bannedUsers, BannedCollaboratorResource::class);
    }
}
