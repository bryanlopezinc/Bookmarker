<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\HttpException;
use App\Models\SecondaryEmail as Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteEmailController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = $request->input('email');

        if ($request->user('api')->email === $email) {
            throw new HttpException(['message' => 'CannotRemovePrimaryEmail'], JsonResponse::HTTP_BAD_REQUEST);
        }

        Model::query()
            ->where('user_id', auth('api')->id())
            ->get(['email', 'id'])
            ->where('email', $email)
            ->whenEmpty(fn () => throw HttpException::notFound(['message' => 'EmailNotFound']))
            ->toQuery()
            ->delete();

        return response()->json();
    }
}
