<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;

class FunctionsTest extends TestCase
{
    public function test_Setting_WillThrowExceptionWhenConfigDoesNotExists(): void
    {
        $this->expectExceptionCode(30_000);

        setting('FOO_BAR');
    }
}
