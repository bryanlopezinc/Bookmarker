<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\Repositories\FetchNotificationResourcesRepository as Repository;
use Illuminate\Notifications\DatabaseNotification;

interface NotificationMapper
{
    public function map(DatabaseNotification $notification, Repository $repository): object;
}
