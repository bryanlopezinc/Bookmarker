<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Mail\FolderCollaborationInviteMail;
use App\Models\FolderAccess;
use App\Models\FolderPermission as Permission;
use App\Models\SecondaryEmail;
use App\ValueObjects\Url;
use Database\Factories\FolderAccessFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\DataTransferObjects\Builders\FolderSettingsBuilder as SettingsBuilder;

class AcceptFolderCollaborationInviteTest extends TestCase
{
    use WithFaker;

    protected function acceptInviteResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('acceptFolderCollaborationInvite', $parameters));
    }

    protected function sendInviteResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('sendFolderCollaborationInvite'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/invite/accept', 'acceptFolderCollaborationInvite');
    }

    public function testUnAuthorizedUserCanAccessRoute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        //should be UnAuthorized if route is protected but returns
        // a assertUnprocessable response because we provided an invalid data.
        $this->acceptInviteResponse()->assertUnprocessable();
    }

    public function testAuthorizedUserCanAccessRoute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->acceptInviteResponse()->assertUnprocessable();
    }

    public function testUnAuthorizedClientCannotAccessRoute(): void
    {
        $this->acceptInviteResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->acceptInviteResponse([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'invite_hash' => 'The invite hash field is required.'
            ]);
    }

    public function testAttributesMustBeValid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->acceptInviteResponse(['invite_hash' => $this->faker->word])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'invite_hash' => 'The invite hash must be a valid UUID.'
            ]);
    }

    public function testWillNotCreatePermissionWhenInvitationHasExpired(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder, $user) {
            Passport::actingAs($user);

            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->travel(25)->hours(function () use ($parameters) {
            $this->acceptInviteResponse($parameters)
                ->assertNotFound()
                ->assertExactJson([
                    'message' => 'Invitation not found or expired'
                ]);
        });

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folder->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function testWillNotCreatePermissionWhenInvitationWasNeverSent(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->acceptInviteResponse(['invite_hash' => $this->faker->uuid])
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Invitation not found or expired'
            ]);
    }

    public function testWillAcceptInviteWithPermissions(): void
    {
        //Will give only view-bookmarks permission if no permissions were specified
        $this->assertWillAcceptInvite([], function (Collection $savedPermissions) {
            $this->assertCount(1, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['addBookmarks'], function (Collection $savedPermissions) {
            $this->assertCount(2, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::ADD_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['removeBookmarks'], function (Collection $savedPermissions) {
            $this->assertCount(2, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::DELETE_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['inviteUser'], function (Collection $savedPermissions) {
            $this->assertCount(2, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::INVITE));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['updateFolder'], function (Collection $savedPermissions) {
            $this->assertCount(2, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::UPDATE_fOLDER));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['removeBookmarks', 'addBookmarks'], function (Collection $savedPermissions) {
            $this->assertCount(3, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::DELETE_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::ADD_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['*'], function (Collection $savedPermissions) {
            $this->assertCount(5, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::DELETE_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::ADD_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::UPDATE_fOLDER));
            $this->assertTrue($savedPermissions->containsStrict(Permission::INVITE));
        });
    }

    private function assertWillAcceptInvite(array $permissions, \Closure $assertion): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);
        $parameters = [
            'email' => $invitee->email,
            'folder_id' => $folder->id,
        ];

        if (!empty($permissions)) {
            $parameters['permissions'] = implode(',', $permissions);
        }

        $parameters = $this->extractInviteUrlParameters(function () use ($user, $parameters) {
            Passport::actingAs($user);
            $this->sendInviteResponse($parameters)->assertOk();
        });

        try {
            $this->acceptInviteResponse($parameters)->assertCreated();

            $savedPermissions = FolderAccess::query()->where([
                'folder_id' => $folder->id,
                'user_id' => $invitee->id,
            ])->get();

            $savedPermissionsTypes = Permission::query()
                ->findMany($savedPermissions->pluck('permission_id')->all(), ['name'])
                ->pluck('name');

            $assertion($savedPermissionsTypes);
        } catch (\Throwable $e) {
            $this->appendMessageToException(
                '******** EXPECTATION FAILED FOR REQUEST WITH PERMISSIONS : ' . implode(',', $permissions) . ' ********',
                $e
            );

            throw $e;
        }
    }

    public function testCannotAccept_Invite_MoreThanOnce(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder, $user) {
            Passport::actingAs($user);

            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        $this->acceptInviteResponse($parameters)
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson([
                'message' => 'Invitation already accepted'
            ]);
    }

    public function testWhenMultipleInvitesWereSent(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $firstInviteParameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder, $user) {
            Passport::actingAs($user);

            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $secondInviteParameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder, $user) {
            Passport::actingAs($user);

            $this->travel(2)->minutes(function () use ($invitee, $folder) {
                $this->sendInviteResponse([
                    'email' => $invitee->email,
                    'folder_id' => $folder->id,
                ])->assertOk();
            });
        });

        $this->acceptInviteResponse($firstInviteParameters)->assertCreated();
        $this->acceptInviteResponse($secondInviteParameters)
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson([
                'message' => 'Invitation already accepted'
            ]);
    }

    public function testWhenMultipleInvitesWereSentToDifferentEmails(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        SecondaryEmail::query()->create([
            'email' => $inviteeSecondaryEmail = $this->faker->unique()->email,
            'user_id' => $invitee->id,
            'verified_at' => now()
        ]);

        $firstInviteParameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder, $user) {
            Passport::actingAs($user);
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $secondInviteParameters = $this->extractInviteUrlParameters(function () use ($inviteeSecondaryEmail, $folder, $user) {
            Passport::actingAs($user);
            $this->sendInviteResponse([
                'email' => $inviteeSecondaryEmail,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->acceptInviteResponse($firstInviteParameters)->assertCreated();
        $this->acceptInviteResponse($secondInviteParameters)
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson([
                'message' => 'Invitation already accepted'
            ]);
    }

    public function testWillNotCreatePermissionWhenFolderHasBeenDeleted(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        Passport::actingAs($user);

        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder) {
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->deleteJson(route('deleteFolder'), ['folder' => $folder->id])->assertOk();

        $this->acceptInviteResponse($parameters)
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'The folder does not exists'
            ]);

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folder->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function testWillNotCreatePermissionWhenInviteeHasDeletedAccount(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder, $user) {
            Passport::actingAs($user);

            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        Passport::actingAs($invitee);
        $this->deleteJson(route('deleteUserAccount'), ['password' => 'password'])->assertOk();
        $this->acceptInviteResponse($parameters)
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'User not found'
            ]);;

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folder->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function testWillNotCreatePermissionWhenInviterHasDeletedAccount(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        Passport::actingAs($user);

        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder) {
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->deleteJson(route('deleteUserAccount'), ['password' => 'password'])->assertOk();
        $this->acceptInviteResponse($parameters)
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'User not found'
            ]);;

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folder->id,
            'user_id' => $invitee->id,
        ]);
    }

    private function extractInviteUrlParameters(\Closure $action): array
    {
        config([
            'settings.ACCEPT_INVITE_URL' => $this->faker->url . '?invite_hash=:invite_hash'
        ]);

        $parameters = [];
        Mail::fake();
        $action();

        Mail::assertQueued(function (FolderCollaborationInviteMail $mail) use (&$parameters) {
            $parts = (new Url($mail->inviteUrl()))->parseQuery();
            $parameters['invite_hash'] = $parts['invite_hash'];

            return true;
        });

        return $parameters;
    }

    public function testWillNotNotifyFolderOwnerWhenInvitationWasSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        Notification::fake();

        Passport::actingAs($folderOwner);
        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder) {
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotifyFolderOwnerWhenInvitationWasNotSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create();

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Passport::actingAs($collaborator);
        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder) {
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        $notificationData = DatabaseNotification::query()->where('notifiable_id', $folder->user_id)->sole(['data'])->data;

        $this->assertEquals($notificationData, [
            'new_collaborator_id' => $invitee->id,
            'folder_id' => $folder->id,
            'added_by' => $collaborator->id,
        ]);
    }

    public function testWillNotNotifyFolderOwnerWhenNotificationsIsDisabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->setting(fn (SettingsBuilder $b) => $b->disableNotifications())
            ->create();

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Notification::fake();

        Passport::actingAs($collaborator);
        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder) {
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwnerWhenNotificationsIsDisabled_andInviteWasSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->setting(fn (SettingsBuilder $b) => $b->disableNotifications())
            ->create(['user_id' => $folderOwner->id]);

        Notification::fake();

        Passport::actingAs($folderOwner);
        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder) {
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwnerWhenNewCollaboratorNotificationIsDisabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->setting(fn (SettingsBuilder $b) => $b->disableNewCollaboratorNotification())
            ->create();

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Notification::fake();

        Passport::actingAs($collaborator);
        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder) {
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwnerWhenNewCollaboratorNotificationIsDisabled_andInviteWasSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->setting(fn (SettingsBuilder $b) => $b->disableNewCollaboratorNotification())
            ->create(['user_id' => $folderOwner->id]);

        Notification::fake();

        Passport::actingAs($folderOwner);
        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder) {
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwnerWhen_onlyCollaboratorsInvitedByMe_Notification_enabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->setting(fn (SettingsBuilder $b) => $b->enableOnlyCollaboratorsInvitedByMeNotification())
            ->create();

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Notification::fake();

        Passport::actingAs($collaborator);
        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder) {
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWill_NotifyFolderOwnerWhen_onlyCollaboratorsInvitedByMe_Notification_enabled_andInviteWasSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->setting(fn (SettingsBuilder $b) => $b->enableOnlyCollaboratorsInvitedByMeNotification())
            ->create(['user_id' => $folderOwner->id]);

        Notification::fake();

        Passport::actingAs($folderOwner);
        $parameters = $this->extractInviteUrlParameters(function () use ($invitee, $folder) {
            $this->sendInviteResponse([
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ])->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        Notification::assertTimesSent(1, \App\Notifications\NewCollaboratorNotification::class);
    }
}
