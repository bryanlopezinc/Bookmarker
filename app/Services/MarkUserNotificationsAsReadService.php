<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use App\Repositories\NotificationRepository;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use Illuminate\Notifications\DatabaseNotification as Notification;
use Illuminate\Support\Collection;

final class MarkUserNotificationsAsReadService
{
    public function __construct(private NotificationRepository $repository)
    {
    }

    /**
     * @param array<Uuid> $notificationIDs
     */
    public function markAsRead(array $notificationIDs): void
    {
        $authUserID = UserID::fromAuthUser();

        collect($this->repository->findManyByIDs($notificationIDs))
            ->tap(function (Collection $notifications) use ($notificationIDs) {
                $allNotificationsExist = $notifications->count() === count($notificationIDs);
                if (!$allNotificationsExist) {
                    throw HttpException::notFound(['message' => 'notification not found']);
                }
            })
            ->each(function (Notification $notification) use ($authUserID) {
                $notificationBelongsToAuthUser = $authUserID->value() === $notification->notifiable_id;
                if (!$notificationBelongsToAuthUser) {
                    throw HttpException::notFound(['message' => 'notification not found']);
                }
            })
            ->filter(fn (Notification $notification) => is_null($notification->read_at))
            ->whenNotEmpty(fn (Collection $notifications) => $this->repository->markAsRead($notifications->all()));
    }
}
