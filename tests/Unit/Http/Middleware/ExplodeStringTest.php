<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ExplodeString;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ExplodeStringTest extends TestCase
{
    public function testWillConvertAttributes(): void
    {
        $validatorFactory = $this->getMockBuilder(Factory::class)->getMock();
        $validator = $this->getMockBuilder(Validator::class)->getMock();

        $validator->method('validate')->willReturn([]);
        $validatorFactory->method('make')->willReturn($validator);

        $request = new Request(['foo' => 'bar,baz,far']);

        (new ExplodeString($validatorFactory))->handle($request, function () {
        }, 'foo');

        $this->assertEquals(['bar', 'baz', 'far'], $request->input('foo'));
    }
}
