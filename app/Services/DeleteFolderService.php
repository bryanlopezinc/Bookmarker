<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\ValueObjects\ResourceID;
use App\Repositories\FoldersRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

final class DeleteFolderService
{
    public function __construct(private FoldersRepository $repository)
    {
    }

    public function delete(ResourceID $folderID): void
    {
        $this->validateFolder($folderID);

        $this->repository->delete($folderID);
    }

    private function validateFolder(ResourceID $folderID): void
    {
        $folder = $this->repository->findByID($folderID);

        if (!$folder) {
            throw new HttpResponseException(response()->json([
                'message' => "The folder does not exists"
            ], Response::HTTP_NOT_FOUND));
        }

        (new EnsureAuthorizedUserOwnsResource)($folder);
    }
}