<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class PreventsDuplicatePostRequestMiddleware
{
    private readonly Repository $repository;
    private readonly Factory $validatorFactory;

    public function __construct(Repository $repository = null, Factory $validatorFactory = null)
    {
        $this->repository = $repository ?: Cache::store();
        $this->validatorFactory = $validatorFactory ?: app(Factory::class);
    }

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        $key = 'idempotency_key';

        if (!$request->isMethod('POST') || !$request->hasHeader($key)) {
            return $next($request);
        }

        $validator = $this->validatorFactory->make(
            [$key => $request->header($key)],
            [$key => ['sometimes', 'string', 'filled', 'max:64']]
        );

        $idempotencyKey = $validator->validate()[$key];

        if ($this->repository->has($idempotencyKey)) {
            return $this->repository->get($idempotencyKey);
        }

        $response = $next($request);

        if ($this->shouldCacheResponse($response)) {
            $this->repository->put($idempotencyKey, $response, now()->addDay());
        }

        return $response;
    }

    protected function shouldCacheResponse(Response $response): bool
    {
        return $response->getStatusCode() !== Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
