<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\CreateFolderRequest;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\FoldersRepository;
use App\ValueObjects\FolderDescription;
use App\ValueObjects\FolderName;
use App\ValueObjects\ResourceID;
use App\Exceptions\FolderNotFoundHttpResponseException as HttpException;

final class UpdateFolderService
{
    public function __construct(private FoldersRepository $foldersRepository)
    {
    }

    public function fromRequest(CreateFolderRequest $request): void
    {
        $folder = $this->foldersRepository->findOrFail(ResourceID::fromRequest($request, 'folder'), new HttpException);

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $this->foldersRepository->update(
            $folder->folderID,
            new FolderName($request->validated('name', $folder->name->value)),
            new FolderDescription($request->validated('description', $folder->description->value))
        );
    }
}
