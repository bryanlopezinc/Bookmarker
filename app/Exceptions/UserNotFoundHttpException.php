<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

final class UserNotFoundHttpException extends HttpResponseException
{
    public function __construct()
    {
        parent::__construct(response()->json([
            'message' => "User not found"
        ], Response::HTTP_NOT_FOUND));
    }
}
