<?php

namespace Tests\Feature;

use App\Notifications\NewCollaboratorNotification;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MarkUserNotificationsAsReadTest extends TestCase
{
    use WithFaker;

    protected function readNotificationsResponse(array $data = []): TestResponse
    {
        return $this->patchJson(route('markNotificationsAsRead'), $data);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/notifications/read', 'markNotificationsAsRead');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->readNotificationsResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->make());

        $this->readNotificationsResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => ['The ids field is required.']]);
    }

    public function testNotification_Ids_mustBeFilled(): void
    {
        Passport::actingAs(UserFactory::new()->make());

        $this->readNotificationsResponse(['ids' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => ['The ids must be a string.']]);
    }

    public function testParametersMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->make());

        $this->readNotificationsResponse(['ids' => 'foo,47b69e15-2dce-3f70-8703-bf071ca6fbd1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0' => ['The ids.0 must be a valid UUID.']]);


        $this->readNotificationsResponse(['ids' => ['47b69e15-2dce-3f70-8703-bf071ca6fbd1']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => 'must be a string']);
    }

    public function testNotification_Ids_MustNotBeGreaterThan_15(): void
    {
        $ids = Collection::times(16, fn () => $this->faker->uuid)->implode(',');

        Passport::actingAs(UserFactory::new()->make());
        $this->readNotificationsResponse(['ids' => $ids])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => ['The ids must not have more than 15 items.']]);
    }

    public function testNotification_Ids_MustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->make());
        $this->readNotificationsResponse(['ids' => '47b69e15-2dce-3f70-8703-bf071ca6fbd1,47b69e15-2dce-3f70-8703-bf071ca6fbd1'])
            ->assertJsonValidationErrors([
                "ids.0" => ["The ids.0 field has a duplicate value."],
                "ids.1" => ["The ids.1 field has a duplicate value."]
            ]);
    }

    public function testWillMarkNotificationsAsRead(): void
    {
        $user = UserFactory::new()->create();

        Collection::times(3, function () use ($user) {
            $user->notify(
                new NewCollaboratorNotification(
                    new UserID(rand(1, 100)),
                    new ResourceID(rand(1, 100)),
                    new UserID(rand(1, 100))
                )
            );
        });

        $notificationIDs = DatabaseNotification::query()->where('notifiable_id', $user->id)->get(['id'])->pluck('id');
        $notRead = $notificationIDs->random();

        Passport::actingAs($user);
        $this->readNotificationsResponse(['ids' => $notificationIDs->reject($notRead)->implode(',')])
            ->assertOk();

        DatabaseNotification::query()
            ->find($notificationIDs->all(), ['read_at', 'id'])
            ->each(function (DatabaseNotification $notification) use ($notRead) {
                if ($notification->id === $notRead) {
                    $this->assertNull($notification->read_at);
                } else {
                    $this->assertNotNull($notification->read_at);
                }
            });
    }

    public function testWillNotAffectOtherUsersNotifications(): void
    {
        [$user, $anotherUser] = UserFactory::times(2)->create();

        $user->notify(
            new NewCollaboratorNotification(
                new UserID(rand(1, 100)),
                new ResourceID(rand(1, 100)),
                new UserID(rand(1, 100))
            )
        );

        $anotherUser->notify(
            new NewCollaboratorNotification(
                new UserID(rand(1, 100)),
                new ResourceID(rand(1, 100)),
                new UserID(rand(1, 100))
            )
        );

        $userNotificationIDs = DatabaseNotification::query()->where('notifiable_id', $user->id)->get(['id'])->pluck('id');

        Passport::actingAs($user);
        $this->readNotificationsResponse(['ids' => $userNotificationIDs->implode(',')])->assertOk();

        DatabaseNotification::select('read_at')
            ->where('notifiable_id', $anotherUser->id)
            ->get()
            ->each(function (DatabaseNotification $notification) {
                $this->assertNull($notification->read_at);
            });
    }

    public function testNotificationsMustBelongToUser(): void
    {
        [$user, $randomUser] = UserFactory::times(2)->create();

        $user->notify(
            new NewCollaboratorNotification(
                new UserID(rand(1, 100)),
                new ResourceID(rand(1, 100)),
                new UserID(rand(1, 100))
            )
        );

        $folderOwnerNotificationIDs = DatabaseNotification::query()->where('notifiable_id', $user->id)->get(['id'])->pluck('id');

        Passport::actingAs($randomUser);
        $this->readNotificationsResponse(['ids' => $folderOwnerNotificationIDs->implode(',')])->assertNotFound();
    }

    public function testWhenNotificationAreAlreadyMarkedAsRead(): void
    {
        $user = UserFactory::new()->create();

        $user->notify(
            new NewCollaboratorNotification(
                new UserID(rand(1, 100)),
                new ResourceID(rand(1, 100)),
                new UserID(rand(1, 100))
            )
        );

        $notificationIDs = DatabaseNotification::select('id')->where('notifiable_id', $user->id)->get()->pluck('id');

        Passport::actingAs($user);
        $this->readNotificationsResponse($data = ['ids' => $notificationIDs->implode(',')])->assertOk();
        $this->readNotificationsResponse($data)->assertOk();
        $this->readNotificationsResponse($data)->assertOk();
    }

    public function testWhenNotificationsDoesNotExist(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->readNotificationsResponse(['ids' => $this->faker->uuid])->assertNotFound();
    }
}
