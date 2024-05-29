<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Http\Resources\FilterFolderResource;
use App\Models\Folder;
use App\Models\Scopes\WherePublicIdScope;
use App\Rules\FolderFieldsRule;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class FetchFolderController extends Controller
{
    public function __invoke(Request $request, string $folderId): FilterFolderResource
    {
        $request->validate([
            'fields' => ['sometimes', new FolderFieldsRule()]
        ]);

        $folder = Folder::query()
            ->withCount(['collaborators', 'bookmarks'])
            ->tap(new WherePublicIdScope(FolderPublicId::fromRequest($folderId)))
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        return new FilterFolderResource($folder);
    }
}
