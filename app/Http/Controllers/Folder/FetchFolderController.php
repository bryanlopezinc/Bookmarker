<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\FolderResource;
use App\Policies\EnsureAuthorizedUserOwnsResource as Policy;
use App\Repositories\Folder\FolderRepository;
use App\Rules\ResourceIdRule;
use App\ValueObjects\ResourceID;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class FetchFolderController extends Controller
{
    public function __invoke(Request $request, FolderRepository $repository): FolderResource
    {
        $request->validate(['id' => ['required', new ResourceIdRule]]);

        (new Policy)($folder = $repository->find(ResourceID::fromRequest($request)));

        return new FolderResource($folder);
    }
}