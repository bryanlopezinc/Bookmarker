<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function testHomePageWillReturnNotFoundResponse(): void
    {
        $this->getJson('/')->assertNotFound();
        $this->get('/')->assertNotFound();
    }
}
