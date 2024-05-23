<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder\LogsActivity;

use App\Actions\CreateFolderActivity;
use App\DataTransferObjects\Activities\FolderIconChangedActivityLogData;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\ActivityType;
use App\Models\Folder;

final class FolderIconChangedActivityLogger
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $createFolderActivity = new CreateFolderActivity(ActivityType::ICON_CHANGED);

        if ( ! $this->data->isUpdatingIcon) {
            return;
        }

        $createFolderActivity->create($folder, new FolderIconChangedActivityLogData($this->data->authUser));
    }
}
