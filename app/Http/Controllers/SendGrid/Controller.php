<?php

declare(strict_types=1);

namespace App\Http\Controllers\SendGrid;

use Illuminate\Http\JsonResponse;
use App\Http\Requests\SendGrid\Request;
use App\Services\CreateBookmarkFromMailMessageService as Service;

final class Controller
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $service->create($request->input('email'));

        return response()->json();
    }
}
