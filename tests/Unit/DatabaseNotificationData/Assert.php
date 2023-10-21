<?php

namespace Tests\Unit\DatabaseNotificationData;

use App\Enums\NotificationType;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\DatabaseNotification;

trait Assert
{
    private function canBeSavedToDB(array $data, NotificationType $type): bool
    {
        try {
            DatabaseNotification::query()->create([
                'id'               => \Illuminate\Support\Str::uuid()->toString(),
                'type'             => $type->value,
                'notifiable_type'  => 'user',
                'notifiable_id'    => rand(1, PHP_INT_MAX),
                'data'             => $data
            ]);

            return true;
        } catch (QueryException $e) {
            $this->assertStringContainsString("Check constraint 'validate_notification_data' is violated", $e->getMessage());
            return false;
        }
    }
}
