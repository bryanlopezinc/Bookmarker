<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Arr;

final class FolderIconUpdatedNotification extends AbstractFolderUpdatedNotification
{
    public function __construct(Folder $folder, User $collaborator)
    {
        parent::__construct($folder, $collaborator, 'icon_path');
    }

    protected function getChanges(): array
    {
        return Arr::except(parent::getChanges(), 'from');
    }
}
