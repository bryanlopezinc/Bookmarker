<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\NotificationType;
use App\ValueObjects\DatabaseNotificationData;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use Carbon\Carbon;

final class DatabaseNotification extends DataTransferObject
{
    use Constructor;

    public readonly Uuid $id;
    public readonly NotificationType $notificationType;
    public readonly UserID $notifiableID;
    public readonly DatabaseNotificationData $notificationData;
    public readonly bool $isUnread;
    public readonly Carbon $readAt;
}
