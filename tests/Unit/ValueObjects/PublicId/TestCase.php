<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\PublicId;

use App\Contracts\IdGeneratorInterface;
use App\NanoIdGenerator;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    #[Before]
    public function registerBindings(): void
    {
        app()->bind(IdGeneratorInterface::class, fn () => new NanoIdGenerator());
    }

    protected function getGenerator(): IdGeneratorInterface
    {
        return app(IdGeneratorInterface::class);
    }
}
