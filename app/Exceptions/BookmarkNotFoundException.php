<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

final class BookmarkNotFoundException extends HttpResponseException
{
    public function __construct()
    {
        parent::__construct(response()->json([
            'message' => "The bookmark does not exists"
        ], Response::HTTP_NOT_FOUND));
    }
}
