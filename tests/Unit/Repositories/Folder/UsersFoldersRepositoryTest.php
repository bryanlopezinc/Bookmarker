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
        $foldersBookmarksCountTable = [];

        FolderFactory::new()->count(10)->create([
            'user_id' => $userID = UserFactory::new()->create()->id
        ])->each(function (Model $folder) use (&$foldersBookmarksCountTable) {
            FolderBookmarksCount::create([
                'folder_id' => $folder->id,
                'count' => $foldersBookmarksCountTable[$folder->id] = rand(1, 200)
            ]);
        });

        (new UsersFoldersRepository)
            ->fetch(new UserID($userID), PaginationData::new())
            ->getCollection()
            ->each(function (Folder $folder) use ($foldersBookmarksCountTable) {
                $this->assertEquals(
                    $foldersBookmarksCountTable[$folder->folderID->toInt()],
                    $folder->storage->total
                );
            });
    }
}
