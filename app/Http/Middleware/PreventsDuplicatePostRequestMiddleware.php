<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class PreventsDuplicatePostRequestMiddleware
{
    private readonly Repository $repository;
    private readonly Factory $validatorFactory;
    private readonly Application $application;

    public function __construct(
        Repository $repository = null,
        Factory $validatorFactory = null,
        Application $application = null
    ) {
        $this->repository = $repository ?: Cache::store();
        $this->validatorFactory = $validatorFactory ?: app(Factory::class);
        $this->application = $application ?: app();
    }

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    final public function handle(Request $request, \Closure $next)
    {
        if (!$request->isMethod('POST')) {
            throw new LogicException(sprintf('Cannot use middleware in %s request', $request->method()));
        }

        if ($this->application->environment('local')) {
            return $next($request);
        }

        $validator = $this->validatorFactory->make(
            $request->only('request_id'),
            ['request_id' => ['required', 'uuid']]
        );

        $requestId = $validator->validate()['request_id'];

        if ($this->repository->has($requestId)) {
            return $this->requestAlreadyCompletedResponse($request);
        }

        $response = $next($request);

        if ($this->isSuccessful($response)) {
            $this->repository->put($requestId, true, now()->addDay());
        }

        return $response;
    }

    protected function requestAlreadyCompletedResponse(Request $request): JsonResponse
    {
        $data = [
            'message'    => 'RequestAlreadyCompleted',
            'info'       => 'A request with the provider request id has already been completed.',
            'request_id' => $request->input('request_id')
        ];

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }

    protected function isSuccessful(Response $response): bool
    {
        return $response->isSuccessful();
    }
}
