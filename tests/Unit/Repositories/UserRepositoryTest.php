<?php

namespace Tests\Unit\Repositories;

use App\Models\User as Model;
use App\Models\UserResourcesCount;
use App\QueryColumns\UserQueryColumns;
use App\Repositories\UserRepository;
use App\ValueObjects\Username;
use Database\Factories\UserFactory;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    public function testBookmarksCountWillBeZeroWhenUserHasNoBookmarks(): void
    {
        /** @var Model */
        $model = UserFactory::new()->create();

        $this->assertSame(0, (new UserRepository)->findByUsername(Username::fromString($model->username))->bookmarksCount->value);
    }

    public function testWillReturnCorrectBookmarksCountWhenUserHasBookmarks(): void
    {
        /** @var Model */
        $model = UserFactory::new()->create();

        UserResourcesCount::query()->create([
            'user_id' => $model->id,
            'count' => 100,
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ]);

        $this->assertSame(100, (new UserRepository)->findByUsername(Username::fromString($model->username))->bookmarksCount->value);
    }

    public function testWillReturnOnlyRequestedColumns(): void
    {
        /** @var Model */
        $model = UserFactory::new()->create();
        $repository = new UserRepository;
        $columns = new UserQueryColumns();

        $user1 = $repository->findByUsername(Username::fromString($model->username), $columns->password()->id()->email());
        $this->assertCount(3, $user1->toArray());
        $user1->password; //will throw initialization exception if values are not set
        $user1->email;
        $user1->id;

        $user2 = $repository->findByUsername(Username::fromString($model->username), $columns->clear()->bookmarksCount()->username());
        $this->assertCount(2, $user2->toArray());
        $user2->bookmarksCount;
        $user2->username;
    }
}
