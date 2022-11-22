<?php

declare(strict_types=1);

namespace App\Notifications;

trait FormatDatabaseNotification
{
    protected function formatNotificationData(array $databaseData): array
    {
        return array_merge(['version' => '1.0.0'], $databaseData);
    }
}
