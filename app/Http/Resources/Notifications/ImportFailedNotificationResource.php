<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\DataTransferObjects\Notifications\ImportFailedNotificationData;
use App\Import\ImportBookmarksStatus;
use Illuminate\Http\Resources\Json\JsonResource;

final class ImportFailedNotificationResource extends JsonResource
{
    public function __construct(private ImportFailedNotificationData $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $data = $this->notification->notification->data;

        return [
            'type' => 'ImportFailedNotification',
            'attributes' => [
                'id'         => $this->notification->notification->id,
                'import_id'  => $data['import_id'],
                'reason'     => ImportBookmarksStatus::from($data['reason'])->reason(),
                'message'    => ImportBookmarksStatus::from($data['reason'])->toNotificationMessage(),
                'notified_on' => $this->notification->notification->created_at->toDateTimeString(), //@phpstan-ignore-line
            ]
        ];
    }
}
