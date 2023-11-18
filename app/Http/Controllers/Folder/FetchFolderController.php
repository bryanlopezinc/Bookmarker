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

        $folder = Folder::onlyAttributes()->find($request->integer('id'));

        FolderNotFoundException::throwIf(!$folder);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        return new FilterFolderResource($folder);
    }
}
