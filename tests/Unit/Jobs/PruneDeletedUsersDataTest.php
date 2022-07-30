<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\PruneDeletedUsersData;
use App\Models\Bookmark;
use App\Models\BookmarkHealth;
use App\Models\DeletedUser;
use App\Models\Favourite;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use App\Models\Taggable;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PruneDeletedUsersDataTest extends TestCase
{
    use WithFaker;

    public function test_will_delete_user_records(): void
    {
        DeletedUser::truncate();

        $user = UserFactory::new()->create();
        $tags = TagFactory::new()->count(5)->create(['created_by' => $user->id]);

        DeletedUser::query()->create(['user_id' => $user->id]);

        $userFolders = FolderFactory::new()
            ->count(10)
            ->create(['user_id' => $user->id])
            ->each(function (Folder $folder) {
                FolderBookmarksCount::query()->create([
                    'folder_id' => $folder->id,
                    'count' => 1
                ]);
            });

        $userBookmarks = BookmarkFactory::new()
            ->count(10)
            ->create(['user_id' => $user->id])
            ->each(function (Bookmark $bookmark, int $index) use ($tags, $userFolders) {
                BookmarkHealth::query()->create([
                    'bookmark_id' => $bookmark->id,
                    'is_healthy'  => $this->faker->boolean(3),
                    'last_checked' => now()
                ]);

                Taggable::query()->create([
                    'taggable_id' => $bookmark->id,
                    'taggable_type' => Taggable::BOOKMARK_TYPE,
                    'tag_id' => $tags->random()->id,
                ]);

                Favourite::query()->create([
                    'bookmark_id' => $bookmark->id,
                    'user_id' => $bookmark->user_id
                ]);

                FolderBookmark::query()->create([
                    'bookmark_id' => $bookmark->id,
                    'folder_id' => $userFolders->get($index)->id,
                    'is_public' => 0
                ]);
            });

        (new PruneDeletedUsersData)->handle();

        $this->assertFalse(Folder::query()->where('user_id', $user->id)->exists());
        $this->assertFalse(Bookmark::query()->where('user_id', $user->id)->exists());
        $this->assertFalse(Favourite::query()->where('user_id', $user->id)->exists());

        $userFolders->each(function (Folder $folder) {
            $this->assertDatabaseMissing(FolderBookmarksCount::class, ['folder_id' => $folder->id]);
            $this->assertDatabaseMissing(FolderBookmark::class, ['folder_id' => $folder->id]);
        });

        $userBookmarks->each(function (Bookmark $bookmark) {
            $this->assertDatabaseMissing(BookmarkHealth::class, ['bookmark_id' => $bookmark->id]);
            $this->assertDatabaseMissing(Taggable::class, ['taggable_id' => $bookmark->id]);
            $this->assertDatabaseMissing(FolderBookmark::class, ['bookmark_id' => $bookmark->id]);
        });
    }
}
