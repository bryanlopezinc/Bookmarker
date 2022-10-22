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
use Database\Factories\FolderAccessFactory;
use Illuminate\Foundation\Testing\WithFaker;

class SendFolderCollaborationInviteTest extends TestCase
{
    use WithFaker;

    protected function sendInviteResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('sendFolderCollaborationInvite', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/invite', 'sendFolderCollaborationInvite');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->sendInviteResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->sendInviteResponse()
            ->assertJsonMissingValidationErrors(['permissions'])
            ->assertJsonValidationErrors([
                'email' => ['The email field is required'],
                'folder_id' => ['The folder id field is required']
            ]);
    }

    public function testEmailAttributeMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->sendInviteResponse([
            'email' => 'foo'
        ])->assertJsonValidationErrors([
            'email' => ['The email must be a valid email address.'],
        ]);
    }

    public function testPermissionsAttributeMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->sendInviteResponse([
            'permissions' => ['bar', 'foo']
        ])->assertJsonValidationErrors([
            'permissions' => ['The permissions must be a string.'],
        ]);

        $this->sendInviteResponse([
            'permissions' => 'view,foo'
        ])->assertJsonValidationErrors([
            'permissions' => ['The selected permissions is invalid.'],
        ]);
    }

    public function testPermissionsAttributeMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->sendInviteResponse([
            'permissions' => 'addBookmarks,addBookmarks'
        ])->assertJsonValidationErrors([
            'permissions.0' => ['The permissions.0 field has a duplicate value.'],
            'permissions.1' => ['The permissions.1 field has a duplicate value.'],
        ]);
    }

    public function testPermissionsAttributeCannotBeHaveAnyOtherValueWhenSetToAll(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->sendInviteResponse([
            'email' => $this->faker->unique()->email,
            'folder_id' => 44,
            'permissions' => '*,addBookmarks'
        ])->assertJsonValidationErrors([
            'permissions' => ['The permissions field cannot contain any other value with the * wildcard.'],
        ]);
    }

    public function testInviteeMustBeARegisteredUser(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->sendInviteResponse([
            'email' => $this->faker->unique()->email,
            'folder_id' => $folder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'User not found']);
    }

    public function testFolderMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->sendInviteResponse([
            'folder_id' => FolderFactory::new()->create()->id + 1,
            'email' => UserFactory::new()->create()->email,
        ])->assertNotFound()
            ->assertExactJson(['message' => "The folder does not exists"]);
    }

    public function testFolderMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->sendInviteResponse([
            'folder_id' => $folder->id,
            'email' => UserFactory::new()->create()->email,
        ])->assertForbidden();
    }

    public function testCannotSendInviteToExistingCollaborator(): void
    {
        [$user, $invitee] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        Passport::actingAs($user);

        FolderAccessFactory::new()->user($invitee->id)->folder($folder->id)->create();

        $this->sendInviteResponse([
            'email' => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson(['message' => "User already a collaborator"]);
    }

    public function testCanOnlySendOneInvitePerMinuteToSameEmail(): void
    {
        [$user, $invitee, $secondInvitee] = UserFactory::new()->count(3)->create();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        SecondaryEmail::query()->create([
            'email' => $inviteeSecondaryEmail = $this->faker->unique()->email,
            'user_id' => $invitee->id,
            'verified_at' => now()
        ]);

        Passport::actingAs($user);

        $this->sendInviteResponse($parameters = [
            'email' => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertOk();

        //same user with same email
        $this->sendInviteResponse($parameters)->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
        $this->sendInviteResponse($parameters)->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        //same user different email
        $this->sendInviteResponse([
            'email' => $inviteeSecondaryEmail,
            'folder_id' => $folder->id,
        ])->assertOk();

        //different user
        $this->sendInviteResponse([
            'email' => $secondInvitee->email,
            'folder_id' => $folder->id,
        ])->assertOk();

        $this->travel(62)->seconds(function () use ($parameters) {
            $this->sendInviteResponse($parameters)->assertOk(); //same user with same email
        });
    }

    public function testCanSendInviteToPrimaryEmail(): void
    {
        Str::createUuidsUsingSequence([$inviteToken = $this->faker->uuid]);

        config([
            'settings.ACCEPT_INVITE_URL' => 'https://laravel.com/docs/9.x/validation?invite_hash=:invite_hash'
        ]);

        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $mailer = Mail::getFacadeRoot();
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        Passport::actingAs($user);
        Mail::fake();

        $this->sendInviteResponse([
            'email' => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertOk();

        Mail::assertQueued(function (FolderCollaborationInviteMail $mail) use ($invitee, $user, $mailer, $folder, $inviteToken) {
            $this->assertSame($invitee->email, $mail->to[0]['address']);

            /** @see https://github.com/laravel/framework/issues/24005#issuecomment-989629711 */
            Mail::swap($mailer);

            $mail->assertSeeInHtml($user->firstname);
            $mail->assertSeeInHtml($user->lastname);
            $mail->assertSeeInHtml($folder->name);
            $mail->assertSeeInHtml('https://laravel.com/docs/9.x/validation?invite_hash=' . $inviteToken);

            return true;
        });
    }

    public function testCanSendInviteToSecondaryEmail(): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        Passport::actingAs($user);

        SecondaryEmail::query()->create([
            'email' => $secondaryEmail = $this->faker->unique()->email,
            'user_id' => $invitee->id,
            'verified_at' => now()
        ]);

        $this->sendInviteResponse([
            'email' => $secondaryEmail,
            'folder_id' => $folder->id,
        ])->assertOk();
    }

    public function testCannotSendInviteToSelf(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->sendInviteResponse([
            'email' => $user->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Cannot send invite to self'
            ]);
    }

    public function testCannotSendInviteToSelfBySecondaryEmail(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        SecondaryEmail::query()->create([
            'email' => $secondaryEmail = $this->faker->unique()->email,
            'user_id' => $user->id,
            'verified_at' => now()
        ]);

        $this->sendInviteResponse([
            'email' => $secondaryEmail,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Cannot send invite to self'
            ]);
    }

    public function testUserWithPermissionCanSendInvite(): void
    {
        [$user, $invitee, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        Passport::actingAs($user);

        FolderAccessFactory::new()
            ->user($user->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        $this->sendInviteResponse([
            'email' => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertOk();
    }

    public function testCollaboratorMustHaveSendInvitePermission(): void
    {
        [$folderOwner, $collaborator, $invitee] = UserFactory::new()->count(3)->create();
        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;
        $folderAccessFactory = FolderAccessFactory::new()->user($collaborator->id)->folder($folderID);

        $folderAccessFactory->updateFolderPermission()->create();
        $folderAccessFactory->addBookmarksPermission()->create();
        $folderAccessFactory->viewBookmarksPermission()->create();
        $folderAccessFactory->removeBookmarksPermission()->create();

        Passport::actingAs($collaborator);
        $this->sendInviteResponse([
            'email' => $invitee->email,
            'folder_id' => $folderID,
        ])->assertForbidden();
    }

    public function testUserWithPermissionCannotSendInviteToFolderOwner(): void
    {
        [$user, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        Passport::actingAs($user);

        FolderAccessFactory::new()
            ->user($user->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        $this->sendInviteResponse([
            'email' => $folderOwner->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Cannot send invitation to folder owner'
            ]);
    }

    public function testUserWithPermissionCannotSendInviteToFolderOwnerViaSecondaryEmail(): void
    {
        [$user, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        Passport::actingAs($user);

        FolderAccessFactory::new()
            ->user($user->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        SecondaryEmail::query()->create([
            'email' => $secondaryEmail = $this->faker->unique()->email,
            'user_id' => $folderOwner->id,
            'verified_at' => now()
        ]);

        $this->sendInviteResponse([
            'email' => $secondaryEmail,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Cannot send invitation to folder owner'
            ]);
    }

    public function testOnlyFolderOwnerCanSendInviteWithPermissions(): void
    {
        [$user, $invitee, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        Passport::actingAs($user);

        FolderAccessFactory::new()
            ->user($user->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        $this->sendInviteResponse([
            'email' => $invitee->email,
            'folder_id' => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'only folder owner can send invites with permissions'
            ]);
    }

    public function testCanSendInviteWithPermissions(): void
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

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

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

    public function test_user_with_permission_cannot_send_invites_when_folder_owner_has_deleted_account(): void
    {
        [$collaborator, $invitee, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Passport::actingAs($folderOwner);
        $this->deleteJson(route('deleteUserAccount'), ['password' => 'password'])->assertOk();

        Passport::actingAs($collaborator);
        $this->sendInviteResponse([
            'email' => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertNotFound()
            ->assertExactJson(['message' => "The folder does not exists"]);
    }
}
