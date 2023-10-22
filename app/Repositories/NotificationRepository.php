<?php

declare(strict_types=1);

namespace App\Repositories;

use App\PaginationData;
use Illuminate\Notifications\DatabaseNotification as NotificationModel;
use Illuminate\Pagination\Paginator;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class NotificationRepository
{
    public function notify(int $userID, Notification $notification): void
    {
        $notifiable = new User(['id' => $userID]);

        $notifiable->notify($notification);
    }

    /**
     * @return Paginator<Object>
     */
    public function unread(int $userID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $notifications = NotificationModel::select(['data', 'type', 'id', 'notifiable_id', 'created_at'])
            ->unRead()
            ->where('notifiable_id', $userID)
            ->latest()
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        $notificationsResources = (new FetchNotificationResourcesRepository($notifications->getCollection()));

        return $notifications->setCollection(
            $notifications->getCollection()->map(new SelectNotificationObject($notificationsResources))
        );
    }
}
