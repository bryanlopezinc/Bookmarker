<?php

declare(strict_types=1);

namespace App\Http\Middleware;

final class ConvertNestedValuesToArrayMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, \Closure $next, ...$keys)
    {
        $converted = [];

        foreach ($keys as $key) {
            if (!$request->has($key)) {
                continue;
            }

            $request->validate([$key => ['string']]);

            $converted[$key] = explode(',', $request->input($key));
        }

        $request->merge($converted);

        return $next($request);
    }

    public static function keys(): string
    {
        return 'convertNestedValues:' . implode(',', func_get_args());
    }
}
