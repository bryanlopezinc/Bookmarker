<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateFolder;

use App\DataTransferObjects\CreateFolderData;

interface HandlerInterface
{
    public function create(CreateFolderData $data): void;
}
