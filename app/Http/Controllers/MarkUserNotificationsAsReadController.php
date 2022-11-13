<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MarkUserNotificationsAsReadService as Service;
use App\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MarkUserNotificationsAsReadController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'ids' => ['array', 'max:15', 'required', 'filled'],
            'ids.*' => ['uuid', 'distinct:strict'],
        ]);

        $service->markAsRead(
            array_map(fn (string $id) => new Uuid($id), $request->input('ids'))
        );

        return response()->json();
    }
}
