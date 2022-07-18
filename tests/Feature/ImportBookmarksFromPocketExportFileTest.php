<?php

namespace Tests\Feature;

use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Models\Bookmark;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ImportBookmarksFromPocketExportFileTest extends TestCase
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
            'source' => 'pocketExportFile',
            'pocket_export_file' => UploadedFile::fake()->create('file.html', 5001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'pocket_export_file' => ['The pocket export file must not be greater than 5000 kilobytes.']
            ]);
    }

    public function testWillImportBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        Passport::actingAs($user = UserFactory::new()->create());

        $content = file_get_contents(base_path('tests/stubs/imports/pocketExportFile.html'));

        $this->getTestResponse([
            'source' => 'pocketExportFile',
            'pocket_export_file' => UploadedFile::fake()->createWithContent('file.html', $content),
        ])->assertStatus(Response::HTTP_PROCESSING);

        $this->assertEquals(Bookmark::query()->where('user_id', $user->id)->count(), 11);
    }
}
