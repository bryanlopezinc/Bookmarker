<?php

namespace Tests\Unit\Repositories;

use App\Models\BookmarksCount;
use App\Models\User;
use App\Repositories\UserRepository;
use App\ValueObjects\Username;
use Database\Factories\UserFactory;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    public function testBookmarksCountWillBeZeroWhenUserHasNoBookmarks(): void
    {
        /** @var User */
        $model = UserFactory::new()->create();

        $this->assertSame(0, (new UserRepository)->findByUsername(new Username($model->username))->bookmarksCount->value);
    }

    public function testWillReturnCorrectBookmarksCountWhenUserHasBookmarks(): void
    {
        /** @var User */
        $model = UserFactory::new()->create();

        BookmarksCount::query()->create(['user_id' => $model->id, 'count' => 100]);

        $this->assertSame(100, (new UserRepository)->findByUsername(new Username($model->username))->bookmarksCount->value);
    }
}
