<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\DataTransferObjects\Notifications\ImportFailedNotificationData;
use App\Enums\NotificationType;
use App\Repositories\FetchNotificationResourcesRepository;
use Illuminate\Notifications\DatabaseNotification;

final class SimpleNotificationMapper implements NotificationMapper
{
    public function map(DatabaseNotification $notification, FetchNotificationResourcesRepository $repository): object
    {
        return match ($type = NotificationType::from($notification->type)) {
            NotificationType::IMPORT_FAILED => new ImportFailedNotificationData($notification),
            default => throw new \RuntimeException("Cannot map notification type {$type->value}")
        };
    }
}
