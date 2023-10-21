<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\DeleteOrphanRecords;
use App\Models\Bookmark;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DeleteOrphanRecordsTest extends TestCase
{
    use WithFaker;

    public function testWillDeleteOnlyDeletedUserBookmarks(): void
    {
        $user = UserFactory::new()->create();

        $bookmark = BookmarkFactory::new()->create();
        $userBookmark = BookmarkFactory::new()->for($user)->create();

        $user->delete();
        
        (new DeleteOrphanRecords)->handle();

        $this->assertDatabaseMissing(Bookmark::class, ['id' => $userBookmark->id]);
        $this->assertDatabaseHas(Bookmark::class, ['id' => $bookmark->id]);
    }
}
