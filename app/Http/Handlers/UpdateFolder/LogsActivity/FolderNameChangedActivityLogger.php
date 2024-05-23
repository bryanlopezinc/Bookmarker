<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder\LogsActivity;

use App\Actions\CreateFolderActivity;
use App\Models\Folder;
use App\DataTransferObjects\Activities\FolderNameChangedActivityLogData;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\ActivityType;

final class FolderNameChangedActivityLogger
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $createFolderActivity = new CreateFolderActivity(ActivityType::NAME_CHANGED);

        if ( ! $this->data->isUpdatingName) {
            return;
        }

        $createFolderActivity->create(
            $folder,
            new FolderNameChangedActivityLogData(
                $this->data->authUser,
                $folder->name->value,
                $this->data->name
            )
        );
    }
}
