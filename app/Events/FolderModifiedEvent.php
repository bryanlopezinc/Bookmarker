<?php

declare(strict_types=1);

namespace App\Events;

use App\ValueObjects\ResourceID;

final class FolderModifiedEvent
{
    public function __construct(public readonly ResourceID $folderID)
    {
    }
}