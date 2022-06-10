<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\PaginationData;
use App\ValueObjects\UserID;
use App\Models\Folder as Model;
use App\DataTransferObjects\Folder;
use Database\Factories\UserFactory;
use App\Models\FolderBookmarksCount;
use Database\Factories\FolderFactory;
use App\Repositories\UsersFoldersRepository;
use Illuminate\Foundation\Testing\WithFaker;

class UsersFoldersRepositoryTest extends TestCase
{
    use WithFaker;

    public function testWillFetchCorrectBookmarksCount(): void
    {
        FolderFactory::new()->count(10)->create([
            'user_id' => $userID = UserFactory::new()->create()->id
        ])->each(function (Model $folder) {
            FolderBookmarksCount::create([
                'folder_id' => $folder->id,
                'count' => $folder->id
            ]);
        });

        (new UsersFoldersRepository)
            ->fetch(new UserID($userID), PaginationData::new())
            ->getCollection()
            ->each(function (Folder $folder) {
                $this->assertEquals($folder->folderID->toInt(), $folder->bookmarksCount->value);
            });
    }
}
