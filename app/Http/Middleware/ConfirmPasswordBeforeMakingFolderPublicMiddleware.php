<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

final class ConfirmPasswordBeforeMakingFolderPublicMiddleware
{
    public function __construct(private Hasher $hasher)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        //Proceed if intent is not to make folder public.
        if (!$request->boolean('is_public')) return $next($request);

        //Proceed if password was recently confirmed.
        if (Cache::get($this->key(), false)) return $next($request);

        if (!$request->has('password')) {
            return response()->json(['message' => 'Password confirmation required.'], Response::HTTP_LOCKED);
        }

        $request->validate([
            'password' => ['string', 'filled']
        ]);

        //The auth middleware ensures a user always returned
         // @phpstan-ignore-next-line
        if (!$this->hasher->check($request->input('password'), auth('api')->user()->getAuthPassword())) {
            return response()->json(['message' => 'Invalid password'], Response::HTTP_UNAUTHORIZED);
        }

        Cache::put($this->key(), true, now()->addHour());

        return $next($request);
    }

    private function key(): string
    {
        return 'folderUpdate.confirmPassword' . auth('api')->id();
    }
}
