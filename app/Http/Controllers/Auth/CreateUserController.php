<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\RegisteredEvent;
use App\Http\Requests\CreateUserRequest;
use App\Services\CreateUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CreateUserController
{
    public function __invoke(CreateUserRequest $request, CreateUserService $service): JsonResponse
    {
        event(new RegisteredEvent($service->FromRequest($request)));

        return response()->json(status: Response::HTTP_CREATED);
    }
}
