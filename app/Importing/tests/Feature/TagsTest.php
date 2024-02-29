<?php

namespace App\Importing\tests\Feature;

use App\Models\Bookmark;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TagsTest extends TestCase
{
    protected function importBookmarkResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('importBookmark'), $parameters);
    }

    #[Test]
    public function willImportBookmarksWithTags(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->withRequestId();

        $view = View::file(__DIR__ . '/../stubs/chromeExportFile.blade.php')
            ->with(['includeBookmarksBar' => false])
            ->with('bookmarks', [
                ['url' => 'https://www.askapache.com/htaccess/'],
                ['url' => 'Invalid url that should not be imported']
            ]);

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'html'  => UploadedFile::fake()->createWithContent('file.html', $view->render()),
            'tags'  => 'fromChrome'
        ])->assertStatus(Response::HTTP_PROCESSING);

        /** @var Bookmark */
        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEquals($userBookmark->tags->sole()->name, 'fromChrome');
    }

    #[Test]
    public function willNotIncludeBookmarkTags(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->withRequestId();

        $view = $this->getViewInstance()
            ->with('bookmarks', [['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => 'vhs99,horror']])
            ->render();

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'html'  => UploadedFile::fake()->createWithContent('file.html', $view),
            'include_bookmark_tags'  => false
        ])->assertStatus(Response::HTTP_PROCESSING);

        /** @var Bookmark */
        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEquals($userBookmark->tags->all(), []);
    }

    #[Test]
    public function willSliceImportFileTagsWhenTagsCountIsGreaterThan15(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->withRequestId();
        $tags = $this->generateTags(16);

        $view = $this->getViewInstance()
            ->with('bookmarks', [['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => implode(',', $tags)]])
            ->render();

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'html'  => UploadedFile::fake()->createWithContent('file.html', $view),
        ])->assertStatus(Response::HTTP_PROCESSING);

        /** @var Bookmark */
        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEqualsCanonicalizing($userBookmark->tags->pluck('name')->all(), array_slice($tags, 0, 15));
    }

    #[Test]
    public function willSkipBookmarkWhenImportFileTagsWhenTagsCountIsGreaterThan15(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->withRequestId();
        $tags = $this->generateTags(16);

        $view = $this->getViewInstance()
            ->with('bookmarks', [
                ['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => implode(',', $tags)],
                ['url' => 'https://www.rottentomatoes.com/m/evil-dead-rise', 'tags' => '']
            ])
            ->render();

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'html'  => UploadedFile::fake()->createWithContent('file.html', $view),
            'bookmark_tags_exceeded' => 'skip_bookmark',
        ])->assertStatus(Response::HTTP_PROCESSING);

        /** @var Bookmark */
        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEquals($userBookmark->url, 'https://www.rottentomatoes.com/m/evil-dead-rise');
    }

    #[Test]
    public function willFailImportWhenImportFileTagsWhenTagsCountIsGreaterThan15(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->withRequestId();
        $tags = $this->generateTags(16);

        $view = $this->getViewInstance()
            ->with('bookmarks', [
                ['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => implode(',', $tags)],
                ['url' => 'https://www.rottentomatoes.com/m/evil-dead-rise', 'tags' => '']
            ])
            ->render();

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'html'  => UploadedFile::fake()->createWithContent('file.html', $view),
            'bookmark_tags_exceeded' => 'fail_import',
        ])->assertStatus(Response::HTTP_PROCESSING);

        $userBookmark = Bookmark::query()->where('user_id', $user->id)->get();

        $this->assertEquals($userBookmark->all(), []);
    }

    #[Test]
    public function willIncludeImportFileTags(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $view = $this->getViewInstance()
            ->with('bookmarks', [['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => 'vhs99,horror']])
            ->render();

        $this->withRequestId();
        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $view),
        ])->assertStatus(Response::HTTP_PROCESSING);

        /** @var Bookmark */
        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEqualsCanonicalizing($userBookmark->tags->pluck('name')->all(), ['vhs99', 'horror']);
    }

    private function getViewInstance()
    {
        return View::file(__DIR__ . '/../stubs/firefox.blade.php')
            ->with(['includeBookmarksInPersonalToolBar' => false])
            ->with('bookmarks', [
                ['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => ''],
                ['url' => 'fake', 'tags' => '']
            ]);
    }

    #[Test]
    public function willMergeImportAndUserTags(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $view = $this->getViewInstance()
            ->with('bookmarks', [['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => 'vhs99,horror']])
            ->render();

        $this->withRequestId();
        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $view),
            'tags' => 'vhs99,movies,horror'  //some tags already exits in import file tags
        ])->assertStatus(Response::HTTP_PROCESSING);

        /** @var Bookmark */
        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEqualsCanonicalizing($userBookmark->tags->pluck('name')->all(), ['vhs99', 'horror', 'movies']);
    }

    #[Test]
    public function willSkipBookmarkWhenTagIsInvalid(): void
    {
        $this->loginUser($user = UserFactory::new()->create());
        $invalidTag = str_repeat('F', 23);

        $view = $this->getViewInstance()
            ->with('bookmarks', [
                ['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => "vhs99,horror,$invalidTag"],
                ['url' => 'https://www.rottentomatoes.com/m/no-one-will-save-you', 'tags' => "vhs99,horror"]
            ])
            ->render();

        $this->withRequestId();
        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $view),
            'invalid_bookmark_tag' => 'skip_bookmark'
        ])->assertStatus(Response::HTTP_PROCESSING);

        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEquals($userBookmark->url, 'https://www.rottentomatoes.com/m/no-one-will-save-you');
    }

    #[Test]
    public function willFailImportWhenTagIsInvalid(): void
    {
        $this->loginUser($user = UserFactory::new()->create());
        $invalidTag = str_repeat('F', 23);

        $view = $this->getViewInstance()
            ->with('bookmarks', [
                ['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => "vhs99,horror,$invalidTag"],
                ['url' => 'https://www.rottentomatoes.com/m/no-one-will-save-you', 'tags' => "vhs99,horror"]
            ])
            ->render();

        $this->withRequestId();
        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $view),
            'invalid_bookmark_tag' => 'fail_import'
        ])->assertStatus(Response::HTTP_PROCESSING);

        $userBookmarks = Bookmark::query()->with('tags')->where('user_id', $user->id)->get();

        $this->assertEquals($userBookmarks->all(), []);
    }

    #[Test]
    public function willSkipBookmarkWhenTagsCannotBeMerged(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $view = $this->getViewInstance()
            ->with('bookmarks', [
                ['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => 'one,two,three,four,five,six,seven,eight,nine,ten,eleven,twelve,thirteen'],
                ['url' => 'https://www.rottentomatoes.com/m/no-one-will-save-you', 'tags' => "vhs99,horror"]
            ])
            ->render();

        $this->withRequestId();
        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $view),
            'tags' => 'fourteen,fifteen,sixteen',
            'tags_merge_overflow' => 'skip_bookmark'
        ])->assertStatus(Response::HTTP_PROCESSING);

        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEquals($userBookmark->url, 'https://www.rottentomatoes.com/m/no-one-will-save-you');
    }

    #[Test]
    public function willFailImportWhenTagsCannotBeMerged(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $view = $this->getViewInstance()
            ->with('bookmarks', [
                ['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => 'one,two,three,four,five,six,seven,eight,nine,ten,eleven,twelve,thirteen'],
                ['url' => 'https://www.rottentomatoes.com/m/no-one-will-save-you', 'tags' => "vhs99,horror"]
            ])
            ->render();

        $this->withRequestId();
        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $view),
            'tags' => 'fourteen,fifteen,sixteen',
            'tags_merge_overflow' => 'fail_import'
        ])->assertStatus(Response::HTTP_PROCESSING);

        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->get();

        $this->assertEquals($userBookmark->all(), []);
    }

    #[Test]
    public function willIgnoreAllTagsWhenTagsCannotBeMerged(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $view = $this->getViewInstance()
            ->with('bookmarks', [
                ['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => 'one,two,three,four,five,six,seven,eight,nine,ten,eleven,twelve,thirteen'],
            ])
            ->render();

        $this->withRequestId();
        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $view),
            'tags' => 'fourteen,fifteen,sixteen',
            'tags_merge_overflow' => 'ignore_all_tags'
        ])->assertStatus(Response::HTTP_PROCESSING);

        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEquals($userBookmark->tags->all(), []);
    }

    #[Test]
    public function mergeUserTagsFirst(): void
    {
        $this->testStrategy([
            'userDefinedTags' => $this->generateTags(14),
            'description' => 'Assert user_defined_tags_first is the default strategy',
            'importFileTags'  => $this->generateTags(5),
            'expectation'  => function (Collection $savedTags, Collection $userDefinedTags, Collection $importFileTags) {
                $this->assertEqualsCanonicalizing($savedTags->all(), $userDefinedTags->add($importFileTags->first())->all());
            }
        ]);

        $this->testStrategy([
            'userDefinedTags' => $this->generateTags(15),
            'description' => 'will not include import file tags if user defined tags is equal to MAX_BOOKMARKS_COUNT',
            'importFileTags'  => $this->generateTags(5),
            'merge_strategy' => 'user_defined_tags_first',
            'expectation'  => function (Collection $savedTags, Collection $userDefinedTags, Collection $importFileTags) {
                $this->assertEqualsCanonicalizing($savedTags->all(), $userDefinedTags->all());
            }
        ]);
    }

    private function testStrategy(array $data): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $view = $this->getViewInstance()
            ->with('bookmarks', [['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => implode(',', $data['importFileTags'])]])
            ->render();

        $parameters = [
            'source' => 'firefoxFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $view),
            'tags' =>  implode(',', $data['userDefinedTags']),
        ];

        if (array_key_exists('merge_strategy', $data)) {
            $parameters['merge_strategy'] = $data['merge_strategy'];
        }

        $this->withRequestId();
        $this->importBookmarkResponse($parameters)->assertStatus(Response::HTTP_PROCESSING);

        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $data['expectation'](
            $userBookmark->tags->pluck('name'),
            collect($data['userDefinedTags']),
            collect($data['importFileTags'])
        );
    }

    private function generateTags(int $amount): array
    {
        return TagFactory::times($amount)->make()->pluck('name')->all();
    }

    #[Test]
    public function mergeImportFileTagsFirst(): void
    {
        $this->testStrategy([
            'userDefinedTags' => $this->generateTags(5),
            'merge_strategy' => 'import_file_tags_first',
            'importFileTags'  => $this->generateTags(14),
            'expectation'  => function (Collection $savedTags, Collection $userDefinedTags, Collection $importFileTags) {
                $this->assertEqualsCanonicalizing($savedTags->all(), $importFileTags->add($userDefinedTags->first())->all());
            }
        ]);

        $this->testStrategy([
            'userDefinedTags' => $this->generateTags(5),
            'description' => 'will not include user tags if import file tags is equal to MAX_BOOKMARKS_COUNT',
            'importFileTags'  => $this->generateTags(15),
            'merge_strategy' => 'import_file_tags_first',
            'expectation'  => function (Collection $savedTags, Collection $userDefinedTags, Collection $importFileTags) {
                $this->assertEqualsCanonicalizing($savedTags->all(), $importFileTags->all());
            }
        ]);
    }
}
