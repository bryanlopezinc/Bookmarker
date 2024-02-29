<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Folder;

interface FolderRequestHandlerInterface
{
    public function handle(Folder $folder): void;
}
