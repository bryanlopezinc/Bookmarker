<?php

namespace Tests\Unit\Repositories\Folder;

use Tests\TestCase;
use App\PaginationData;
use App\ValueObjects\UserID;
use App\Models\Folder as Model;
use App\DataTransferObjects\Folder;
use Database\Factories\UserFactory;
use App\Models\FolderBookmarksCount;
use Database\Factories\FolderFactory;
use App\Repositories\Folder\UsersFoldersRepository;
use Illuminate\Foundation\Testing\WithFaker;

class UsersFoldersRepositoryTest extends TestCase
{
    use WithFaker;

    public function testWillFetchCorrectBookmarksCount(): void
    {
        $foldersBookmarksCount = [];

        FolderFactory::new()->count(10)->create([
            'user_id' => $userID = UserFactory::new()->create()->id
        ])->each(function (Model $folder) use (&$foldersBookmarksCount) {
            FolderBookmarksCount::create([
                'folder_id' => $folder->id,
                'count' => $foldersBookmarksCount[$folder->id] = rand(1, 200)
            ]);
        });

        (new UsersFoldersRepository)
            ->fetch(new UserID($userID), PaginationData::new())
            ->getCollection()
            ->each(function (Folder $folder) use ($foldersBookmarksCount) {
                $this->assertEquals(
                $foldersBookmarksCount[$folder->folderID->toInt()],
                    $folder->storage->total
                );
            });
    }
}
