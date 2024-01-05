<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Import\ImportBookmarksStatus;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

final class ImportFailedNotificationResource extends JsonResource
{
    public function __construct(private DatabaseNotification $notification)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        $data = $this->notification->data;

        return [
            'type' => 'ImportFailedNotification',
            'attributes' => [
                'id'          => $this->notification->id,
                'import_id'   => $data['import_id'],
                'reason'      => ImportBookmarksStatus::from($data['reason'])->reason(),
                'notified_on' => $this->notification->created_at->toDateTimeString(), //@phpstan-ignore-line
            ]
        ];
    }
}
