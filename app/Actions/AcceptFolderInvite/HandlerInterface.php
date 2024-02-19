<?php

declare(strict_types=1);

namespace App\Actions\AcceptFolderInvite;

use App\Models\Folder;
use App\Exceptions\AcceptFolderInviteException;

interface HandlerInterface
{
    /**
     * @throws AcceptFolderInviteException
     */
    public function handle(Folder $folder): void;
}
