<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\DataTransferObjects\DatabaseNotification;
use App\Enums\NotificationType;
use App\ValueObjects\DatabaseNotificationData;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use Carbon\Carbon;

final class DatabaseNotificationBuilder extends Builder
{
    public static function new(array $attributes = []): self
    {
        return new self($attributes);
    }

    public function id(string|Uuid $uuid): self
    {
        $this->attributes['id'] =  is_string($uuid) ? new Uuid($uuid) : $uuid;

        return $this;
    }

    public function type(string $type): self
    {
        $this->attributes['notificationType'] = NotificationType::from($type);

        return $this;
    }

    public function notifiableID(int $id): self
    {
        $this->attributes['notifiableID'] = new UserID($id);

        return $this;
    }

    public function data(array $data): self
    {
        $this->attributes['notificationData'] = new DatabaseNotificationData($data);

        return $this;
    }

    public function readAt(?Carbon $readAt): self
    {
        $isUnread = is_null($readAt);
        $this->attributes['isUnread'] = $isUnread;

        if ($isUnread) {
            return $this;
        }

        $this->attributes['readAt'] =  $readAt;

        return $this;
    }

    public function build(): DatabaseNotification
    {
        return new DatabaseNotification($this->attributes);
    }
}
