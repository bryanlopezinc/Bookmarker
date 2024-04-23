<?php

declare(strict_types=1);

namespace App\Importing\Http\Resources;

use App\Importing\DataTransferObjects\ImportFailedNotificationData;
use App\Importing\Enums\ImportBookmarksStatus;
use App\ValueObjects\PublicId\ImportPublicId;
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

        /** @var \Carbon\Carbon */
        $notifiedOn = $this->notification->notification->created_at;

        return [
            'type' => 'ImportFailedNotification',
            'attributes' => [
                'id'         => $this->notification->notification->id,
                'import_id'  => (new ImportPublicId($data['public_id']))->present(),
                'reason'     => ImportBookmarksStatus::from($data['reason'])->reason(),
                'message'    => ImportBookmarksStatus::from($data['reason'])->toNotificationMessage(),
                'notified_on' => $notifiedOn->toDateTimeString(),
            ]
        ];
    }
}
