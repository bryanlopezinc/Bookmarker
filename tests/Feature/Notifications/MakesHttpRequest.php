<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Illuminate\Testing\TestResponse;

trait MakesHttpRequest
{
    protected function fetchNotificationsResponse(array $data = []): TestResponse
    {
        return $this->getJson(route('fetchUserNotifications', $data));
    }
}
