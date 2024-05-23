<?php

declare(strict_types=1);

namespace App\Http\Resources\ResourceMessages;

use App\DataTransferObjects\Activities\FolderNameChangedActivityLogData;
use App\Models\User;
use App\ValueObjects\FolderName;
use JsonSerializable;

final class NameChanged implements JsonSerializable
{
    public function __construct(
        private readonly User $collaborator,
        private readonly FolderNameChangedActivityLogData $activityLog
    ) {
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize(): mixed
    {
        return sprintf('%s changed folder name from %s to %s.', ...[
            $this->collaborator->getFullNameOr($this->activityLog->collaborator)->present(),
            (new FolderName($this->activityLog->oldName))->present(),
            (new FolderName($this->activityLog->newName))->present(),
        ]);
    }
}
