<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notifications;

use Illuminate\Notifications\DatabaseNotification;

abstract class GenericNotification
{
    public function __construct(public readonly DatabaseNotification $notification)
    {
    }
}
