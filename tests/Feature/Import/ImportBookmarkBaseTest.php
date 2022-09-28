<?php

namespace Tests\Feature\Import;

use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ImportBookmarkBaseTest extends TestCase
{
    protected function importBookmarkResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('importBookmark'), $parameters);
    }

    final public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/bookmarks/import', 'importBookmark');
    }

    final public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->importBookmarkResponse()->assertUnauthorized();
    }

    final public function testUserEmailMustBeVerified(): void
    {
        Passport::actingAs(UserFactory::new()->unverified()->create());

        $this->importBookmarkResponse()
            ->assertForbidden()
            ->assertJson([
                'message' => 'Your email address is not verified.'
            ]);
    }
}
