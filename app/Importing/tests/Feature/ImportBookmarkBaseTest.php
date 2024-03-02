<?php

namespace App\Importing\tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ImportBookmarkBaseTest extends TestCase
{
    use WithFaker;

    protected function importBookmarkResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('importBookmark'), $parameters, $headers);
    }

    final public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/bookmarks/import', 'importBookmark');
    }

    final public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->importBookmarkResponse()->assertUnauthorized();
    }
}
