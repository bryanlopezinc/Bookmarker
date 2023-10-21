<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Http\Resources\FilterFolderResource;
use App\Rules\FolderFieldsRule;
use App\Rules\ResourceIdRule;
use App\Services\Folder\FetchFolderService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class FetchFolderController extends Controller
{
    public function __invoke(Request $request, FetchFolderService $repository): FilterFolderResource
    {
        $request->validate([
            'id'     => ['required', new ResourceIdRule()],
            'fields' => ['sometimes', new FolderFieldsRule()]
        ]);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser(
            $folder = $repository->find($request->integer('id'))
        );

        return new FilterFolderResource($folder);
    }
}
