<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use App\Contracts\TransformsNotificationInterface;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationResource extends JsonResource
{
    public function __construct(private TransformsNotificationInterface $notification)
    {
        parent::__construct($notification);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray($request)
    {
        return $this->notification->toJsonResource()->toArray($request);
    }
}
