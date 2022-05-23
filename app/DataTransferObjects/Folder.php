<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\FolderDescription;
use App\ValueObjects\FolderName;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Carbon\Carbon;

final class Folder extends DataTransferObject
{
    public readonly ResourceID $folderID;
    public readonly FolderName $name;
    public readonly UserID $ownerID;
    public readonly FolderDescription $description;
    public readonly Carbon $createdAt;
}
