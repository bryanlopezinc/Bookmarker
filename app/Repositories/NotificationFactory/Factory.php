<?php

declare(strict_types=1);

namespace App\Repositories\NotificationFactory;

use App\Repositories\FetchNotificationResourcesRepository;
use Illuminate\Notifications\DatabaseNotification;

interface Factory
{
    public function create(FetchNotificationResourcesRepository $repository, DatabaseNotification $notification): Object;
}
