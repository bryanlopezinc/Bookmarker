<?php

namespace Tests\Feature\Import;

use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Models\Bookmark;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;

class ImportBookmarksFromFirefoxBrowserTest extends ImportBookmarkBaseTest
{
    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->importBookmarkResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The source field is required.']
            ]);

        $this->importBookmarkResponse(['source' => 'foo'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'source' => ['The selected source is invalid.']
        ]);

        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'firefox_export_file' => UploadedFile::fake()->create('file.html', 5001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'firefox_export_file' => ['The firefox export file must not be greater than 5000 kilobytes.']
            ]);
    }

    public function testImportBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        Passport::actingAs($user = UserFactory::new()->create());

        $content = file_get_contents(base_path('tests/stubs/imports/firefox.html'));

        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'firefox_export_file' => UploadedFile::fake()->createWithContent('file.html', $content),
        ])->assertStatus(Response::HTTP_PROCESSING);

        $this->assertEquals(Bookmark::query()->where('user_id', $user->id)->count(), 6);
    }
}
