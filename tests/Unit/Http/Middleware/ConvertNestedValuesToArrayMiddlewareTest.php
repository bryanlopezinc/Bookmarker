<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\ConvertNestedValuesToArrayMiddleware;

class ConvertNestedValuesToArrayMiddlewareTest extends TestCase
{
    public function testWillConvertAttributes(): void
    {
        $request = request()->merge([
            'foo' => 'bar,baz,far'
        ]);

        (new ConvertNestedValuesToArrayMiddleware)->handle($request, function () {
        }, 'foo');

        $this->assertEquals(['bar', 'baz', 'far'], request('foo'));
    }

    public function testAttributeMustBeAString(): void
    {
        $this->expectExceptionMessage('The foo must be a string.');

        $request = request()->merge([
            'foo' => ['bar,baz,far']
        ]);

        (new ConvertNestedValuesToArrayMiddleware)->handle($request, function () {
        }, 'foo');

        $this->assertEquals(['bar', 'baz', 'far'], request('foo'));
    }
}
