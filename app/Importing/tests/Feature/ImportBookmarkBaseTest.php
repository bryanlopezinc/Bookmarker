<?php

namespace App\Importing\tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ImportBookmarkBaseTest extends TestCase
{
    use WithFaker;

    protected function importBookmarkResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('importBookmark'), $parameters);
    }

    final public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/bookmarks/import', 'importBookmark');
    }

    final public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->importBookmarkResponse()->assertUnauthorized();
    }

    final public function testWillReturnUnprocessableWhenRequestIdIsNotAValidUuid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->importBookmarkResponse(['request_id' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['request_id' => 'The request id must be a valid UUID.']);
    }
}
