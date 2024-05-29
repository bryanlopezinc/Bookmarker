<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder\LogsActivity;

use App\Actions\CreateFolderActivity;
use App\DataTransferObjects\Activities\DescriptionChangedActivityLogData;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\ActivityType;
use App\Models\Folder;

final class FolderDescriptionChangedActivityLogger
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $createFolderActivity = new CreateFolderActivity(ActivityType::DESCRIPTION_CHANGED);

        if ( ! $this->data->isUpdatingDescription) {
            return;
        }

        $createFolderActivity->create(
            $folder,
            new DescriptionChangedActivityLogData(
                $this->data->authUser,
                $folder->description,
                $this->data->description
            )
        );
    }
}
