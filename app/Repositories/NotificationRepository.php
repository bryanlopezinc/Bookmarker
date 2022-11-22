<?php

declare(strict_types=1);

namespace App\Repositories;

use App\PaginationData;
use App\ValueObjects\UserID;
use Illuminate\Notifications\DatabaseNotification as NotificationModel;
use Illuminate\Pagination\Paginator;
use App\Contracts\TransformsNotificationInterface;
use App\DataTransferObjects\Builders\DatabaseNotificationBuilder;
use App\DataTransferObjects\DatabaseNotification;
use App\Models\User;
use App\ValueObjects\Uuid;
use Illuminate\Notifications\Notification;

final class NotificationRepository
{
    public function notify(UserID $userID, Notification $notification): void
    {
        $notifiable = new User(['id' => $userID->value()]);
        $channels = (array)$notification->via($notifiable); // @phpstan-ignore-line

        if (!in_array('database', $channels, true)) {
            throw new \Exception('The notification must include a database channel');
        }

        $notifiable->notify($notification);
    }

    /**
     * @return Paginator<TransformsNotificationInterface>
     */
    public function unread(UserID $userID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $notifications = NotificationModel::select(['data', 'type', 'id', 'notifiable_id'])
            ->unRead()
            ->where('notifiable_id', $userID->value())
            ->latest()
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        $notifications->setCollection($notifications->getCollection()->map($this->mapNotificationFn()));

        $notificationsResources = (new FetchNotificationResourcesRepository($notifications->getCollection()));

        return $notifications->setCollection(
            $notifications->getCollection()->map(new SelectNotificationObject($notificationsResources))
        );
    }

    private function mapNotificationFn(): \Closure
    {
        return function (NotificationModel $notification) {
            return DatabaseNotificationBuilder::new()
                ->id($notification->id)
                ->type($notification->type)
                ->notifiableID($notification->notifiable_id)
                ->data($notification->data)
                ->readAt($notification->read_at)
                ->build();
        };
    }

    /**
     * @param array<Uuid> $notificationIDs
     * @return array<DatabaseNotification>
     */
    public function findManyByIDs(array $notificationIDs): array
    {
        $ids = array_map(fn (Uuid $notificationID) => $notificationID->value, $notificationIDs);

        return NotificationModel::query()
            ->find($ids, ['read_at', 'notifiable_id', 'id', 'type', 'data'])
            ->map($this->mapNotificationFn())
            ->all();
    }

    /**
     * @param array<Uuid> $notificationIDs
     */
    public function markAsRead(array $notificationIDs): void
    {
        NotificationModel::whereIn('id', collect($notificationIDs)->map(fn (Uuid $id) => $id->value))->update(['read_at' => now()]);
    }
}
