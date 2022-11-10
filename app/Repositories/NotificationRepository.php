<?php

declare(strict_types=1);

namespace App\Repositories;

use App\PaginationData;
use App\ValueObjects\UserID;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\Paginator;
use App\Contracts\TransformsNotificationInterface;

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
}
