<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\Chrome;

use App\Importers\Chrome\ImportBookmarksFromChromeBrowser as Importer;
use App\Importers\Chrome\DOMParserInterface;
use App\Importers\FilesystemInterface;
use App\Models\Bookmark;
use App\Models\Tag;
use App\Models\Taggable;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use ArrayIterator;
use Closure;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ImportChromeBookmarksTest extends TestCase
{
    use WithFaker;

    public function testWillThrowExceptionIfFileDoesNotExists(): void
    {
        $this->expectException(FileNotFoundException::class);

        $userID = new UserID(UserFactory::new()->create()->id);

        $this->getImporter()->import($userID, Uuid::generate(), []);
    }

    protected function getImporter(): Importer
    {
        return app(Importer::class);
    }

    public function testWillClearDataAfterImport(): void
    {
        $userID = new UserID(UserFactory::new()->create()->id);
        $requestID = Uuid::generate();
        $DOMParser = $this->getMockBuilder(DOMParserInterface::class)->getMock();

        $this->mockFilesystem(function (MockObject $mock) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn('');
            $mock->expects($this->once())->method('delete');
        });

        $DOMParser->expects($this->once())->method('parse')->willReturn(new ArrayIterator());

        $this->swap(DOMParserInterface::class, $DOMParser);

        $this->getImporter()->import($userID, $requestID, []);
    }

    private function mockFilesystem(Closure $mock): void
    {
        $filesystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();

        $mock($filesystem);

        $this->swap(FilesystemInterface::class, $filesystem);
    }

    public function testWillAttachTagsToBookmarks(): void
    {
        Bus::fake();

        $url = $this->faker->url;
        $userID = new UserID(UserFactory::new()->create()->id);

        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
                    <DT><A HREF=$url ADD_DATE="1505762363">htaccess - Ultimate Apache .htaccess file Guide</A>
            </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->getImporter()->import($userID, Uuid::generate(), [
            'tags' => [$tag = $this->faker->word]
        ]);

        /** @var Bookmark */
        $bookmark = Bookmark::query()->where('user_id', $userID->toInt())->sole();

        $this->assertEquals($bookmark->url, $url);
        $this->assertDatabaseHas(Tag::class, ['name' => $tag]);
        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $bookmark->id,
            'taggable_type' => Taggable::BOOKMARK_TYPE,
            'tagged_by_id' => $userID->toInt()
        ]);
    }

    public function testWillUseChromeBookmarkDateByDefault(): void
    {
        Bus::fake();

        $userID = new UserID(UserFactory::new()->create()->id);

        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
                    <DT><A HREF='https://laravel.com/docs/9.x/requests#files' ADD_DATE="1505762363">htaccess - Ultimate Apache .htaccess file Guide</A>
            </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->getImporter()->import($userID, Uuid::generate(), []);

        /** @var Bookmark */
        $bookmark = Bookmark::query()->where('user_id', $userID->toInt())->sole();

        $this->assertEquals($bookmark->created_at->year, 2017);
    }

    public function testWillNotUseChromeBookmarkDateWhenIndicated(): void
    {
        Bus::fake();

        $userID = new UserID(UserFactory::new()->create()->id);
        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
                    <DT><A HREF='https://laravel.com/docs/9.x/requests#files' ADD_DATE="1505762363">htaccess - Ultimate Apache .htaccess file Guide</A>
            </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->getImporter()->import($userID, Uuid::generate(), [
            'use_timestamp' => false
        ]);

        /** @var Bookmark */
        $bookmark = Bookmark::query()->where('user_id', $userID->toInt())->sole();

        $this->assertTrue($bookmark->created_at->isToday());
    }

    public function testWillUseDefaultDateWhenDateIsInvalid(): void
    {
        Bus::fake();

        $userID = new UserID(UserFactory::new()->create()->id);
        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
                    <DT><A HREF='https://laravel.com/docs/9.x/requests#files' ADD_DATE="3030303003303030303030303030303030303003">htaccess - Ultimate</A>
            </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->getImporter()->import($userID, Uuid::generate(), []);

        /** @var Bookmark */
        $bookmark = Bookmark::query()->where('user_id', $userID->toInt())->sole();

        $this->assertTrue($bookmark->created_at->isToday());
    }

    public function testWillNotSaveBookmarkIfUrlIsInvalid(): void
    {
        $userID = new UserID(UserFactory::new()->create()->id);

        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
                    <DT><A HREF="<sricpt>alert('crsf')</script>" ADD_DATE="1505762363">htaccess - Ultimate</A>
            </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->getImporter()->import($userID, Uuid::generate(), []);

        $this->assertFalse(Bookmark::query()->where('user_id', $userID->toInt())->exists());
    }

    public function testWillStoreBookmarks(): void
    {
        Bus::fake();

        $userID = new UserID(UserFactory::new()->create()->id);

        $this->mockFilesystem(function (MockObject $filesystem) {
            $filesystem->expects($this->once())->method('exists')->willReturn(true);
            $filesystem->expects($this->once())->method('get')->willReturn(
                file_get_contents(base_path('tests/stubs/imports/chromeExportFile.html'))
            );
        });

        $this->getImporter()->import($userID, Uuid::generate(), []);

        $this->assertEquals(Bookmark::query()->where('user_id', $userID->toInt())->count(), 111);
    }
}
