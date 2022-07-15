<?php

declare(strict_types=1);

namespace Tests\Unit\DataTransferObjects\Validator;

use Attribute;
use App\Contracts\AfterDTOSetUpHookInterface;

#[Attribute(Attribute::TARGET_CLASS)]
final class TestValidator implements AfterDTOSetUpHookInterface
{
    public static int $invocationCount = 0;

    public function executeAfterSetUp(Object $bookmark): void
    {
        static::$invocationCount++;
    }
}
