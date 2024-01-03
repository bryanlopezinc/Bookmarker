<?php

namespace Tests\Feature\Import;

use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\View;
use Laravel\Passport\Passport;

class ImportBookmarksFromPocketExportFileTest extends ImportBookmarkBaseTest
{
    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->withRequestId();

        $this->importBookmarkResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The source field is required.']
            ]);

        $this->importBookmarkResponse([
            'source'  => 'pocketExportFile',
            'html' => UploadedFile::fake()->create('file.html', 1001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'html' => ['The html must not be greater than 1000 kilobytes.']
            ]);
    }

    public function testImportBookmarks(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->withRequestId();

        $this->importBookmarkResponse([
            'source' => 'pocketExportFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $this->getViewInstance()->render()),
        ])->assertStatus(Response::HTTP_PROCESSING);
    }

    private function getViewInstance()
    {
        return View::file(base_path('tests/stubs/Imports/pocket.blade.php'))
            ->with('bookmarks', [['tags' => '']]);
    }
}
