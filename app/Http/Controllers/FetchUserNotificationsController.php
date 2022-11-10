<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Notifications\NotificationResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Repositories\NotificationRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;

final class FetchUserNotificationsController
{
    public function __invoke(Request $request, NotificationRepository $repository): PaginatedResourceCollection
    {
        $request->validate([
            ...PaginationData::new()->asValidationRules(),
        ]);

        return new PaginatedResourceCollection(
            $repository->unread(UserID::fromAuthUser(), PaginationData::fromRequest($request)),
            NotificationResource::class
        );
    }
}
