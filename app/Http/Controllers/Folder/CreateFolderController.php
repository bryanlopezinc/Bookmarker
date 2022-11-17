<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\FolderSettings;
use App\Http\Requests\CreateFolderRequest;
use App\Repositories\Folder\CreateFolderRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CreateFolderController
{
    public function __invoke(CreateFolderRequest $request, CreateFolderRepository $repository): JsonResponse
    {
        $folder = (new FolderBuilder())
            ->setCreatedAt(now())
            ->setDescription($request->validated('description'))
            ->setName($request->validated('name'))
            ->setOwnerID(UserID::fromAuthUser())
            ->setIsPublic($request->boolean('is_public'))
            ->setTags(TagsCollection::make($request->validated('tags', [])))
            ->setSettings(FolderSettings::default())
            ->build();

        $repository->create($folder);

        return response()->json(status: Response::HTTP_CREATED);
    }
}
