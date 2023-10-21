<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Database\Factories\UserFactory;
use App\Enums\NotificationType;
use App\Notifications\CollaboratorExitNotification;
use Database\Factories\FolderFactory;
use Illuminate\Notifications\DatabaseNotification;
use Tests\Feature\AssertValidPaginationData;

class FetchUserNotificationsTest extends TestCase
{
    use MakesHttpRequest, AssertValidPaginationData;

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/notifications', 'fetchUserNotifications');
    }

    public function testWillReturnAuthorizedWhenUserIsNotSignedIn(): void
    {
        $this->fetchNotificationsResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'fetchUserNotifications');
    }

    public function testWillReturnEmptyDatasetWhenUserHasNoNotifications(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillFetchOnlyUnReadNotifications(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $data = (new CollaboratorExitNotification(
            $folder->id,
            UserFactory::new()->create()->id,
        ))->toDatabase($user);

        DatabaseNotification::query()->create([
            'id'              => Str::uuid()->toString(),
            'type'            => NotificationType::FOLDER_UPDATED,
            'notifiable_type' => 'user',
            'notifiable_id'   => $user->id,
            'data'            => $data,
            'read_at'         => now()
        ]);

        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
