<?php

declare(strict_types=1);

namespace App\Repositories;

use App\PaginationData;
use App\ValueObjects\UserID;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\Paginator;
use App\Contracts\TransformsNotificationInterface;
use App\ValueObjects\Uuid;
use Illuminate\Support\Arr;

final class NotificationRepository
{
    /**
     * @return Paginator<TransformsNotificationInterface>
     */
    public function unread(UserID $userID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $notifications = DatabaseNotification::select(['data', 'type', 'id'])
            ->unRead()
            ->where('notifiable_id', $userID->value())
            ->latest()
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        $notificationsResources = (new FetchNotificationResourcesRepository($notifications->getCollection()));

        return $notifications->setCollection(
            $notifications->getCollection()->map(new SelectNotificationObject($notificationsResources))
        );
    }

    /**
     * @param array<Uuid> $notificationIDs
     * @return array<DatabaseNotification>
     */
    public function findManyByIDs(array $notificationIDs): array
    {
        $ids = array_map(fn (Uuid $notificationID) => $notificationID->value, $notificationIDs);

        return DatabaseNotification::query()->find($ids, ['read_at', 'notifiable_id', 'id'])->all();
    }

    /**
     * @param array<DatabaseNotification> $notifications
     */
    public function markAsRead(array $notifications): void
    {
        DatabaseNotification::whereIn('id', Arr::pluck($notifications, 'id'))->update(['read_at' => now()]);
    }
}
