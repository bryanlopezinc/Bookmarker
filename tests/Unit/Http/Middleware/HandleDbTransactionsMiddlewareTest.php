<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Middleware\HandleDbTransactionsMiddleware;

class HandleDbTransactionsMiddlewareTest extends TestCase
{
    private const TABLE = 'HandleDbTransactionsMiddlewareTestTable';

    public function setUp(): void
    {
        parent::setUp();

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('value');
        });
    }

    private function performTransaction($value): void
    {
        DB::table(self::TABLE)->insert([
            'value' => $value
        ]);
    }

    public function testWillRollBackTransactionWhenServerErrorOccurs(): void
    {
        $request = request();

        $request->setMethod('POST');

        (new HandleDbTransactionsMiddleware())->handle($request, function () {
            $this->performTransaction('hello');

            return new Response(status: 500);
        });

        $this->assertDatabaseMissing(self::TABLE, ['value' => 'hello']);
    }

    public function testWillCommitTransactionWhenResponseIsSuccessful(): void
    {
        (new HandleDbTransactionsMiddleware())->handle(request(), function () {
            $this->performTransaction('hi');

            return new Response(status: 200);
        });

        $this->assertDatabaseHas(self::TABLE, ['value' => 'hi']);
    }

    public function tearDown(): void
    {
        Schema::dropIfExists(self::TABLE);
        parent::tearDown();
    }
}
