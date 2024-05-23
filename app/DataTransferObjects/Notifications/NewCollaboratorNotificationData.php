<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use App\DataTransferObjects\Activities\InviteAcceptedActivityLogData as ActivityLogData;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

final class NewCollaboratorNotificationData implements Arrayable
{
    public function __construct(
        public readonly User $collaborator,
        public readonly Folder $folder,
        public readonly User $newCollaborator,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $logData = ActivityLogData::fromArray($data);
        $folder = new Folder($data['folder']);

        $folder->exists = true;

        return new NewCollaboratorNotificationData($logData->inviter, $folder, $logData->invitee);
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        $logData = (new ActivityLogData($this->collaborator, $this->newCollaborator))->toArray();

        return array_replace($logData, [
            'version' => '1.0.0',
            'folder'  => $this->folder->activityLogContextVariables()
        ]);
    }
}
