<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Collections\BookmarkPublicIdsCollection;
use App\Collections\RolesPublicIdsCollection;
use App\Contracts\IdGeneratorInterface;
use App\ValueObjects\PublicId\BlacklistedDomainId;
use App\ValueObjects\PublicId\BookmarkPublicId;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\ImportPublicId;
use App\ValueObjects\PublicId\RolePublicId;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Support\Collection;

trait GeneratesId
{
    protected function generator(): IdGeneratorInterface
    {
        return app(IdGeneratorInterface::class);
    }

    protected function generateFolderId(): FolderPublicId
    {
        return new FolderPublicId($this->generator()->generate());
    }

    protected function generateBookmarkId(): BookmarkPublicId
    {
        return new BookmarkPublicId($this->generator()->generate());
    }

    protected function generateBlacklistedDomainId(): BlacklistedDomainId
    {
        return new BlacklistedDomainId($this->generator()->generate());
    }

    protected function generateBookmarkIds(int $times = 1): BookmarkPublicIdsCollection
    {
        return BookmarkPublicIdsCollection::fromObjects(
            Collection::times($times, fn () => $this->generateBookmarkId())
        );
    }

    protected function generateRoleId(): RolePublicId
    {
        return new RolePublicId($this->generator()->generate());
    }

    protected function generateRoleIds(int $times = 1): RolesPublicIdsCollection
    {
        return RolesPublicIdsCollection::fromObjects(
            Collection::times($times, fn () => $this->generateRoleId())
        );
    }

    protected function generateUserId(): UserPublicId
    {
        return new UserPublicId($this->generator()->generate());
    }

    protected function generateImportId(): ImportPublicId
    {
        return new ImportPublicId($this->generator()->generate());
    }
}
