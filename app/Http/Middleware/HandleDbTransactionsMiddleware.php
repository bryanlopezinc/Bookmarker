<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Closure;

final class HandleDbTransactionsMiddleware
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  Closure                   $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, $next)
    {
        $onlyMethods = [
            Request::METHOD_PATCH,
            Request::METHOD_DELETE,
            Request::METHOD_PUT,
            Request::METHOD_POST,
        ];

        if ( ! in_array($request->method(), $onlyMethods, true)) {
            return $next($request);
        }

        DB::beginTransaction();

        /** @var \Illuminate\Http\Response */
        $response = $next($request);

        if ( ! $response->isSuccessful()) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        return $response;
    }
}
