<?php

namespace Tests\Unit\Repositories;

use App\Models\User as Model;
use App\Models\UserBookmarksCount;
use App\Models\UserFavouritesCount;
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

    public function testBookmarksCountWillBeZeroWhenUserHasNoFavourites(): void
    {
        /** @var Model */
        $model = UserFactory::new()->create();

        $this->assertSame(0, (new UserRepository)->findByUsername(Username::fromString($model->username))->favouritesCount->value);
    }

    public function testWillReturnCorrectBookmarksCountWhenUserHasBookmarks(): void
    {
        /** @var Model */
        $model = UserFactory::new()->create();

        UserBookmarksCount::create([
            'user_id' => $model->id,
            'count' => 100,
        ]);

        $this->assertSame(100, (new UserRepository)->findByUsername(Username::fromString($model->username))->bookmarksCount->value);
    }

    public function testWillReturnCorrectBookmarksCountWhenUserHasFavourites(): void
    {
        /** @var Model */
        $model = UserFactory::new()->create();

        UserFavouritesCount::create([
            'user_id' => $model->id,
            'count' => 45,
        ]);

        $this->assertSame(45, (new UserRepository)->findByUsername(Username::fromString($model->username))->favouritesCount->value);
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
