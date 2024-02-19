<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\BroadcastServiceProvider;
use App\Providers\ProvidersCollection;
use App\Providers\TelescopeServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProvidersCollectionTest extends TestCase
{
    #[Test]
    public function willNotIncludeProviders(): void
    {
        $providers = ProvidersCollection::getProviders();

        //will want to remove this test/assertion should we ever stop
        // using telescope.
        $this->assertTrue(class_exists(TelescopeServiceProvider::class));

        $this->assertNotContains(TelescopeServiceProvider::class, $providers);
        $this->assertNotContains(BroadcastServiceProvider::class, $providers);
        $this->assertNotContains(ProvidersCollection::class, $providers);
    }
}
