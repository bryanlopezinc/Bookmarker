<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;

class FunctionsTest extends TestCase
{
    public function testWillThrowExceptionWhenConfigDoesNotExists(): void
    {
        $this->expectExceptionCode(30_000);

        setting('FOO_BAR');
    }
}
