<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification as Notification;

final class MarkUserNotificationsAsReadService
{
    /**
     * @param array<string> $notificationIDs
     */
    public function markAsRead(array $uuids): void
    {
        $authUserID = UserID::fromAuthUser()->value();

        Notification::query()
            ->find($uuids, ['read_at', 'notifiable_id', 'id', 'type', 'data'])
            ->tap(function (Collection $notifications) use ($uuids) {
                if ($notifications->count() !== count($uuids)) {
                    throw HttpException::notFound(['message' => 'NotificationNotFound']);
                }
            })
            ->each(function (Notification $notification) use ($authUserID) {
                if ($authUserID !== $notification->notifiable_id) {
                    throw HttpException::notFound(['message' => 'NotificationNotFound']);
                }
            })
            ->filter(fn (Notification $notification) => $notification->read_at === null)
            ->whenNotEmpty(fn (Collection $notifications) => $notifications->toQuery()->update(['read_at' => now()]));
    }
}
