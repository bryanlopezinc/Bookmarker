<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\PaginationData;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\FolderAttributes;
use App\Repositories\Folder\FetchFolderCollaboratorsRepository;
use App\ValueObjects\ResourceID;
use Illuminate\Pagination\Paginator;

final class FetchFolderCollaboratorsService
{
    public function __construct(
        private FetchFolderCollaboratorsRepository $repository,
        private FolderRepositoryInterface $folderRepository
    ) {
    }

    /**
     * @return Paginator<FolderCollaborator>
     */
    public function get(ResourceID $folderID, PaginationData $pagination): Paginator
    {
        $folder = $this->folderRepository->find($folderID, FolderAttributes::only('id,user_id'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        return $this->repository->collaborators($folderID, $pagination);
    }
}
