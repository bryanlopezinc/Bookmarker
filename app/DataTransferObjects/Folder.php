<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Attributes\EnsureValidTagsCount;
use App\Collections\TagsCollection;
use App\Contracts\BelongsToUserInterface;
use App\ValueObjects\FolderDescription;
use App\ValueObjects\FolderName;
use App\ValueObjects\FolderStorage;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Carbon\Carbon;

#[EnsureValidTagsCount('MAX_FOLDER_TAGS', 'tags')]
final class Folder extends DataTransferObject implements BelongsToUserInterface
{
    public readonly ResourceID $folderID;
    public readonly FolderName $name;
    public readonly UserID $ownerID;
    public readonly FolderDescription $description;
    public readonly Carbon $createdAt;
    public readonly Carbon $updatedAt;
    public readonly FolderStorage $storage;
    public readonly bool $isPublic;
    public readonly TagsCollection $tags;

    public function getOwnerID(): UserID
    {
        return $this->ownerID;
    }
}
