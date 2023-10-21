<?php

namespace Tests\Feature\Import;

use App\Jobs\UpdateBookmarkWithHttpResponse;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;

class ImportBookmarkFromChromeTest extends ImportBookmarkBaseTest
{
    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->importBookmarkResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The source field is required.']
            ]);

        $this->importBookmarkResponse(['source' => 'chromeExportFile',])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'html' => ['The html field is required.']
            ]);

        $this->importBookmarkResponse(['source' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The selected source is invalid.']
            ]);

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'html' => UploadedFile::fake()->create('file.html', 5001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'html' => ['The html must not be greater than 5000 kilobytes.']
            ]);

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'tags'   => TagFactory::new()->count(16)->make()->pluck('name')->implode(',')
        ])->assertJsonValidationErrors([
            'tags' => 'The tags must not be greater than 15 characters.'
        ]);

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'tags' => 'howTo,howTo,stackOverflow'
        ])->assertJsonValidationErrors([
            "tags.0" => ["The tags.0 field has a duplicate value."],
            "tags.1" => ["The tags.1 field has a duplicate value."]
        ]);
    }

    public function testImportBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        Passport::actingAs(UserFactory::new()->create());

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'html'   => UploadedFile::fake()->createWithContent('file.html', file_get_contents(base_path('tests/stubs/imports/chromeExportFile.html'))),
        ])->assertStatus(Response::HTTP_PROCESSING);
    }
}
