<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\CreateFolderRequest;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\FoldersRepository;
use App\ValueObjects\FolderDescription;
use App\ValueObjects\FolderName;
use App\ValueObjects\ResourceID;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

final class UpdateFolderService
{
    public function __construct(private FoldersRepository $foldersRepository)
    {
    }

    public function fromRequest(CreateFolderRequest $request): void
    {
        $folder = $this->foldersRepository->findByID(ResourceID::fromRequest($request, 'folder'));

        if (!$folder) {
            throw new HttpResponseException(response()->json([
                'message' => "The folder does not exists"
            ], Response::HTTP_NOT_FOUND));
        }

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $this->foldersRepository->update(
            $folder->folderID,
            new FolderName($request->validated('name', $folder->name->value())),
            new FolderDescription($request->validated('description', $folder->description->value))
        );
    }
}
