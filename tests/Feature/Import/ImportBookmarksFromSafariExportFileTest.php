<?php

namespace Tests\Feature\Import;

use App\Jobs\UpdateBookmarkWithHttpResponse;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ImportBookmarksFromSafariExportFileTest extends TestCase
{
    private function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('importBookmark'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/bookmarks/import', 'importBookmark');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The source field is required.']
            ]);
    }

    public function testSourceMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['source' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The selected source is invalid.']
            ]);
    }

    public function testFileMustNotBeGreaterThan_5_MegaBytes(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'source' => 'safariExportFile',
            'safari_html' => UploadedFile::fake()->create('file.html', 5001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'safari_html' => ['The safari html must not be greater than 5000 kilobytes.']
            ]);
    }

    public function testCannotAddMoreThan_15_Tags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $tags = TagFactory::new()->count(16)->make()->pluck('name')->implode(',');

        $this->getTestResponse([
            'source' => 'safariExportFile',
            'tags' => $tags
        ])->assertJsonValidationErrors([
            'tags' => 'The tags must not be greater than 15 characters.'
        ]);
    }

    public function testTagsMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'source' => 'safariExportFile',
            'tags' => 'howTo,howTo,stackOverflow'
        ])->assertJsonValidationErrors([
            "tags.0" => ["The tags.0 field has a duplicate value."],
            "tags.1" => ["The tags.1 field has a duplicate value."]
        ]);
    }

    public function testWillImportBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'source' => 'safariExportFile',
            'safari_html' => UploadedFile::fake()->createWithContent('file.html', file_get_contents(base_path('tests/stubs/imports/SafariExportFile.html'))),
        ])->assertStatus(Response::HTTP_PROCESSING);
    }
}
