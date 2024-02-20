<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Http\Resources\FilterFolderResource;
use App\Models\Folder;
use App\Rules\FolderFieldsRule;
use App\Rules\ResourceIdRule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class FetchFolderController extends Controller
{
    public function __invoke(Request $request): FilterFolderResource
    {
        $request->validate([
            'id'     => ['required', new ResourceIdRule()],
            'fields' => ['sometimes', new FolderFieldsRule()]
        ]);

        /** @var Folder|null */
        $folder = Folder::query()->withCount(['collaborators', 'bookmarks'])->find($request->integer('id'));

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        return new FilterFolderResource($folder);
    }
}
