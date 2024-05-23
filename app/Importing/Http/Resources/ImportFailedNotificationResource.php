<?php

declare(strict_types=1);

namespace App\Importing\Http\Resources;

use App\Importing\Enums\ImportBookmarksStatus;
use App\Models\DatabaseNotification;
use App\ValueObjects\PublicId\ImportPublicId;
use Illuminate\Http\Resources\Json\JsonResource;

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
                'id'         => $this->notification->id,
                'import_id'  => (new ImportPublicId($data['public_id']))->present(),
                'reason'     => ImportBookmarksStatus::from($data['reason'])->reason(),
                'message'    => ImportBookmarksStatus::from($data['reason'])->toNotificationMessage(),
                'notified_on' => $this->notification->created_at,
            ]
        ];
    }
}
