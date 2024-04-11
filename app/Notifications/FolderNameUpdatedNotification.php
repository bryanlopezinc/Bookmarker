<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Folder;
use App\Models\User;
use App\ValueObjects\FolderName;

final class FolderNameUpdatedNotification extends AbstractFolderUpdatedNotification
{
    public function __construct(Folder $folder, User $collaborator)
    {
        parent::__construct($folder, $collaborator, 'name');
    }

    protected function getChanges(): array
    {
        /** @var array{from: FolderName, to: FolderName} */
        $changes = parent::getChanges();

        $changes['from'] = (string) $changes['from'];
        $changes['to'] = (string) $changes['to'];

        return $changes;
    }
}
