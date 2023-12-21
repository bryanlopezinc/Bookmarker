<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Contracts\Validation\Factory;

/**
 * Convert request attributes that are comma separated to array.
 * Any attribute with format foo,bar,baz will be converted to array.
 */
final class ExplodeString
{
    private readonly Factory $validatorFactory;

    public function __construct(Factory $validatorFactory = null)
    {
        $this->validatorFactory = $validatorFactory ?: app(Factory::class);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, \Closure $next, string ...$keys)
    {
        $converted = [];

        foreach ($keys as $key) {
            if (!$request->has($key)) {
                continue;
            }

            $this->validatorFactory->make($request->only($key), [$key => ['string']])->validate();

            $converted[$key] = explode(',', $request->input($key));
        }

        $request->merge($converted);

        return $next($request);
    }

    public static function keys(string ...$keys): string
    {
        return 'convertNestedValues:' . implode(',', $keys);
    }
}
