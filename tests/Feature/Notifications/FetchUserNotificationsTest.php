<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Database\Factories\UserFactory;
use App\Notifications\CollaboratorExitNotification;
use App\Notifications\YouHaveBeenBootedOutNotification;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\AssertValidPaginationData;

class FetchUserNotificationsTest extends TestCase
{
    use MakesHttpRequest;
    use AssertValidPaginationData;

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
        $this->loginUser(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'fetchUserNotifications');
    }

    public function testWillReturnEmptyDatasetWhenUserHasNoNotifications(): void
    {
        $this->loginUser(UserFactory::new()->create());
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function filterByUnReadNotifications(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $notifications = [
            new YouHaveBeenBootedOutNotification(FolderFactory::new()->create()),
            (new CollaboratorExitNotification(FolderFactory::new()->create(), UserFactory::new()->create()))
        ];

        $user->notify($notifications[0]);

        $user->notifications()->get()->markAsRead();

        $user->notify($notifications[1]);

        $this->fetchNotificationsResponse(['filter' => 'unread'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'CollaboratorExitNotification');

        $this->fetchNotificationsResponse(['filter' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter' => 'The selected filter is invalid.']);
    }
}
