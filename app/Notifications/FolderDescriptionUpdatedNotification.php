<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Folder;
use App\Models\User;

final class FolderDescriptionUpdatedNotification extends AbstractFolderUpdatedNotification
{
    public function __construct(Folder $folder, User $collaborator)
    {
        parent::__construct($folder, $collaborator, 'description');
    }
}
