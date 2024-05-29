<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\PaginationData;
use Illuminate\Http\Request;
use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Resources\Notifications\NotificationResource;
use Illuminate\Pagination\Paginator;

final class FetchUserNotificationsController
{
    public function __invoke(Request $request): PaginatedResourceCollection
    {
        $request->validate(['filter' => ['sometimes', 'in:unread'], ...PaginationData::new()->asValidationRules()]);

        $pagination = PaginationData::fromRequest($request);

        /** @var Paginator */
        $notifications = User::fromRequest($request)
            ->notifications()
            ->with(['resources'])
            ->select(['data', 'type', 'id', 'notifiable_id', 'created_at'])
            ->when($request->input('filter') === 'unread', fn ($query) => $query->unread())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return new PaginatedResourceCollection($notifications, NotificationResource::class);
    }
}
