<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\CleanDeletedUsersTable;
use App\Models\DeletedUser;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CleanDeletedUsersTableTest extends TestCase
{
    use WithFaker;

    public function test_will_delete_user_when_all_user_data_has_been_deleted(): void
    {
        DeletedUser::truncate();

        $user = UserFactory::new()->create();

        DeletedUser::query()->create(['user_id' => $user->id]);

        (new CleanDeletedUsersTable)->handle();

        $this->assertFalse(DeletedUser::query()->where('user_id', $user->id)->exists());
    }

    public function test_will_not_delete_user_when_all_user_folders_has_not_been_deleted(): void
    {
        DeletedUser::truncate();

        $user = UserFactory::new()->create();

        FolderFactory::new()->for($user)->create();

        DeletedUser::query()->create(['user_id' => $user->id]);

        (new CleanDeletedUsersTable)->handle();

        $this->assertTrue(DeletedUser::query()->where('user_id', $user->id)->exists());
    }

    public function test_will_not_delete_user_when_all_user_bookmarks_has_not_been_deleted(): void
    {
        DeletedUser::truncate();

        $user = UserFactory::new()->create();

        BookmarkFactory::new()->create(['user_id' => $user->id]);

        DeletedUser::query()->create(['user_id' => $user->id]);

        (new CleanDeletedUsersTable)->handle();

        $this->assertTrue(DeletedUser::query()->where('user_id', $user->id)->exists());
    }
}
