<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

final class HttpException extends HttpResponseException
{
    public function __construct(mixed $message, int $status)
    {
        parent::__construct(response()->json($message, $status));
    }

    public static function notFound(mixed $message = []): self
    {
        return new self($message, Response::HTTP_NOT_FOUND);
    }

    public static function conflict(mixed $message = []): self
    {
        return new self($message, Response::HTTP_CONFLICT);
    }
}
