<?php

namespace Tests\Feature\Folder;

use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use App\Models\SecondaryEmail;
use Laravel\Passport\Passport;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use App\Mail\FolderCollaborationInviteMail;
use Database\Factories\FolderCollaboratorPermissionFactory;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\BannedCollaborator;
use Database\Factories\EmailFactory;

class SendFolderCollaborationInviteTest extends TestCase
{
    use WithFaker;

    protected function tearDown(): void
    {
        Str::createUuidsNormally();

        parent::tearDown();
    }

    protected function sendInviteResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('sendFolderCollaborationInvite'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/invite', 'sendFolderCollaborationInvite');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->sendInviteResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->sendInviteResponse()
            ->assertJsonMissingValidationErrors(['permissions'])
            ->assertJsonValidationErrors([
                'email'     => ['The email field is required'],
                'folder_id' => ['The folder id field is required']
            ]);

        $this->sendInviteResponse(['email' => 'foo'])
            ->assertJsonValidationErrors([
                'email' => ['The email must be a valid email address.'],
            ]);

        $this->sendInviteResponse(['permissions' => ['bar', 'foo']])
            ->assertJsonValidationErrors([
                'permissions' => ['The permissions must be a string.'],
            ]);

        $this->sendInviteResponse(['permissions' => 'view,foo'])
            ->assertJsonValidationErrors([
                'permissions' => ['The selected permissions is invalid.'],
            ]);

        $this->sendInviteResponse(['permissions' => 'addBookmarks,addBookmarks'])
            ->assertJsonValidationErrors([
                'permissions.0' => ['The permissions.0 field has a duplicate value.'],
                'permissions.1' => ['The permissions.1 field has a duplicate value.'],
            ]);

        $this->sendInviteResponse([
            'email'       => $this->faker->unique()->email,
            'folder_id'   => 44,
            'permissions' => '*,addBookmarks'
        ])->assertJsonValidationErrors([
            'permissions' => ['The permissions field cannot contain any other value with the * wildcard.'],
        ]);
    }

    public function testWillReturnNotFoundWhenInviteeIsNotARegisteredUser(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->sendInviteResponse([
            'email'     => $this->faker->unique()->email,
            'folder_id' => $folder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->sendInviteResponse([
            'folder_id' => FolderFactory::new()->create()->id + 1,
            'email'     => UserFactory::new()->create()->email,
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->sendInviteResponse([
            'folder_id' => $folder->id,
            'email'     => UserFactory::new()->create()->email,
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnConflictWhenUserIsAlreadyACollaborator(): void
    {
        [$folderOwner, $invitee, $collaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $inviteeSecondaryEmail = EmailFactory::new()->for($invitee)->create();

        FolderCollaboratorPermissionFactory::new()->user($invitee->id)->folder($folder->id)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)->inviteUser()
            ->folder($folder->id)
            ->create();

        Passport::actingAs($folderOwner);
        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($error = ['message' => "UserAlreadyACollaborator"]);

        $this->sendInviteResponse([
            'email'     => $inviteeSecondaryEmail->email,
            'folder_id' => $folder->id,
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($error);


        Passport::actingAs($collaborator);
        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($error);

        $this->sendInviteResponse([
            'email'     => $inviteeSecondaryEmail->email,
            'folder_id' => $folder->id,
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($error);
    }

    public function testCanOnlySendOneInvitePerMinuteToSameEmail(): void
    {
        [$user, $invitee, $secondInvitee] = UserFactory::new()->count(3)->create();

        $folder = FolderFactory::new()->for($user)->create();

        SecondaryEmail::query()->create([
            'email'       => $inviteeSecondaryEmail = $this->faker->unique()->email,
            'user_id'     => $invitee->id,
            'verified_at' => now()
        ]);

        Passport::actingAs($user);
        $this->sendInviteResponse($parameters = [
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertOk();

        //same user with same email
        $this->sendInviteResponse($parameters)->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
        $this->sendInviteResponse($parameters)->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        //same user different email
        $this->sendInviteResponse([
            'email'     => $inviteeSecondaryEmail,
            'folder_id' => $folder->id,
        ])->assertOk();

        //different user
        $this->sendInviteResponse([
            'email'     => $secondInvitee->email,
            'folder_id' => $folder->id,
        ])->assertOk();

        $this->travel(62)->seconds(function () use ($parameters) {
            $this->sendInviteResponse($parameters)->assertOk(); //same user with same email
        });
    }

    public function testSendInviteToPrimaryEmail(): void
    {
        $inviteToken = $this->faker->uuid;

        Str::createUuidsUsing(fn () => $inviteToken);

        config([
            'settings.ACCEPT_INVITE_URL' => 'https://laravel.com/docs/9.x/validation?invite_hash=:invite_hash'
        ]);

        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $mailer = Mail::getFacadeRoot();
        $folder = FolderFactory::new()->for($user)->create();

        Passport::actingAs($user);
        Mail::fake();

        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertOk();

        Mail::assertQueued(function (FolderCollaborationInviteMail $mail) use ($invitee, $user, $mailer, $folder, $inviteToken) {
            $this->assertSame($invitee->email, $mail->to[0]['address']);

            /** @see https://github.com/laravel/framework/issues/24005#issuecomment-989629711 */
            Mail::swap($mailer);

            $mail->assertSeeInHtml($user->first_name);
            $mail->assertSeeInHtml($user->last_name);
            $mail->assertSeeInHtml($folder->name);
            $mail->assertSeeInHtml('https://laravel.com/docs/9.x/validation?invite_hash=' . $inviteToken);

            return true;
        });
    }

    public function testSendInviteToSecondaryEmail(): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        Passport::actingAs($user);

        SecondaryEmail::query()->create([
            'email'       => $secondaryEmail = $this->faker->unique()->email,
            'user_id'     => $invitee->id,
            'verified_at' => now()
        ]);

        $this->sendInviteResponse([
            'email'     => $secondaryEmail,
            'folder_id' => $folder->id,
        ])->assertOk();
    }

    public function testWillReturnForbiddenWhenFolderOwnerIsSendingInviteToSelf(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();
        $secondaryEmail = EmailFactory::new()->for($user)->create();

        $this->sendInviteResponse([
            'email'     => $user->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson($error = ['message' => 'CannotSendInviteToSelf']);

        $this->sendInviteResponse([
            'email'     => $secondaryEmail->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson($error);
    }

    public function testWillReturnForbiddenWhenCollaboratorIsSendingInviteToSelf(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create();
        $secondaryEmail = EmailFactory::new()->for($user)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($user->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        $this->sendInviteResponse([
            'email'     => $user->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson($error = ['message' => 'CannotSendInviteToSelf']);

        $this->sendInviteResponse([
            'email'     => $secondaryEmail->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson($error);
    }

    public function testUserWithPermissionCanSendInvite(): void
    {
        [$collaboratorWithInviteUserPermission, $invitee, $user] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($user)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaboratorWithInviteUserPermission->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Passport::actingAs($collaboratorWithInviteUserPermission);
        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertOk();
    }

    public function testWillReturnForbiddenWhenCollaboratorDoesNotHaveSendInvitePermission(): void
    {
        [$folderOwner, $collaborator, $invitee] = UserFactory::new()->count(3)->create();
        $folderID = FolderFactory::new()->for($folderOwner)->create()->id;
        $folderAccessFactory = FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folderID);

        $folderAccessFactory->updateFolderPermission()->create();
        $folderAccessFactory->addBookmarksPermission()->create();
        $folderAccessFactory->viewBookmarksPermission()->create();
        $folderAccessFactory->removeBookmarksPermission()->create();

        Passport::actingAs($collaborator);
        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folderID,
        ])->assertForbidden()
            ->assertExactJson(['message' => 'NoSendInvitePermission']);
    }

    public function testWillReturnConflictWhenCollaboratorIsSendingInviteToFolderOwner(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $folderOwnerSecondaryEmail = EmailFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Passport::actingAs($collaborator);
        $this->sendInviteResponse([
            'email'    => $folderOwner->email,
            'folder_id' => $folder->id,
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($error = ['message' => 'UserAlreadyACollaborator']);

        $this->sendInviteResponse([
            'email'    => $folderOwnerSecondaryEmail->email,
            'folder_id' => $folder->id,
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($error);
    }

    public function testWillReturnForbiddenWhenCollaboratorIsSendingInviteWithPermissions(): void
    {
        [$collaboratorWithInviteUserPermission, $invitee, $user] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($user)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaboratorWithInviteUserPermission->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Passport::actingAs($collaboratorWithInviteUserPermission);
        $this->sendInviteResponse([
            'email'       => $invitee->email,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CollaboratorCannotSendInviteWithPermissions']);
    }

    public function testSendInviteWithPermissions(): void
    {
        $this->assertCanSendInviteWithPermissions(['*'], 50);
        $this->assertCanSendInviteWithPermissions(['addBookmarks'], 2);
        $this->assertCanSendInviteWithPermissions(['removeBookmarks'], 3);
        $this->assertCanSendInviteWithPermissions(['inviteUser'], 31);
        $this->assertCanSendInviteWithPermissions(['updateFolder'], 32);
        $this->assertCanSendInviteWithPermissions(['addBookmarks', 'removeBookmarks'], 4);
        $this->assertCanSendInviteWithPermissions(['addBookmarks', 'removeBookmarks', 'inviteUser'], 14);
    }

    private function assertCanSendInviteWithPermissions(array $permissions, int $testID): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();

        Passport::actingAs($user);

        $folder = FolderFactory::new()->for($user)->create();

        $parameters = [
            'email' => $invitee->email,
            'folder_id' => $folder->id,
        ];

        if (!empty($permissions)) {
            $parameters['permissions'] = implode(',', $permissions);
        }

        try {
            $this->sendInviteResponse($parameters)->assertOk();
        } catch (\Throwable $e) {
            $this->appendMessageToException(
                '******** EXPECTATION FAILED FOR REQUEST WITH ID : ' . $testID . ' ********',
                $e
            );

            throw $e;
        }
    }

    public function testCollaborator_cannot_send_invites_when_folder_owner_has_deleted_account(): void
    {
        [$collaborator, $invitee, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        $folderOwner->delete();

        Passport::actingAs($collaborator);
        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnForbiddenWhenSendingInviteToBannedUser(): void
    {
        [$collaborator, $bannedCollaborator, $folderOwner] = UserFactory::times(3)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $bannedCollaboratorSecondEmail = EmailFactory::new()->for($bannedCollaborator)->create();

        BannedCollaborator::query()->create([
            'folder_id' => $folder->id,
            'user_id'   => $bannedCollaborator->id
        ]);

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Passport::actingAs($collaborator);
        $this->sendInviteResponse([
            'email'     => $bannedCollaborator->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson($error = ['message' => 'UserBanned']);

        $this->sendInviteResponse([
            'email'     => $bannedCollaboratorSecondEmail->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson($error);

        Passport::actingAs($folderOwner);
        $this->sendInviteResponse([
            'email'     => $bannedCollaborator->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson($error = ['message' => 'UserBanned']);

        $this->sendInviteResponse([
            'email'     => $bannedCollaboratorSecondEmail->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson($error);
    }
}
