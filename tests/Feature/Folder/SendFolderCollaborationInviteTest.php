<?php

namespace Tests\Feature\Folder;

use App\Enums\Permission;
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
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\BannedCollaborator;
use App\Models\Folder;
use App\Services\Folder\ToggleFolderCollaborationRestriction;
use App\UAC;
use Database\Factories\EmailFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;

class SendFolderCollaborationInviteTest extends TestCase
{
    use WithFaker, CreatesCollaboration;

    protected ToggleFolderCollaborationRestriction $toggleFolderCollaborationRestriction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toggleFolderCollaborationRestriction = app(ToggleFolderCollaborationRestriction::class);
    }

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

        $this->sendInviteResponse(['email' => $this->faker->unique()->email, 'folder_id' => $folder->id])
            ->assertNotFound()
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

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);
        $this->CreateCollaborationRecord($invitee, $folder);

        Passport::actingAs($folderOwner);
        $this->sendInviteResponse(['email' => $invitee->email, 'folder_id' => $folder->id])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($error = ['message' => "UserAlreadyACollaborator"]);

        $this->sendInviteResponse(['email' => $inviteeSecondaryEmail->email, 'folder_id' => $folder->id])
            ->assertStatus(Response::HTTP_CONFLICT)
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
        [$folderOwner, $invitee, $secondInvitee] = UserFactory::new()->count(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        SecondaryEmail::query()->create([
            'email'       => $inviteeSecondaryEmail = $this->faker->unique()->email,
            'user_id'     => $invitee->id,
            'verified_at' => now()
        ]);

        Passport::actingAs($folderOwner);
        $this->sendInviteResponse($parameters = ['email' => $invitee->email, 'folder_id' => $folder->id])->assertOk();

        $this->sendInviteResponse($parameters)
            ->assertTooManyRequests()
            ->assertJsonPath('retry-after', 60);

        $this->sendInviteResponse($parameters)->assertTooManyRequests();

        //Assert will not rate limit user when sending invite to invitee secondary email
        $this->sendInviteResponse(['email' => $inviteeSecondaryEmail, 'folder_id' => $folder->id,])->assertOk();

        //Assert will not rate limit user when sending invite to different user
        $this->sendInviteResponse(['email' => $secondInvitee->email, 'folder_id' => $folder->id])->assertOk();

        //Assert user can send invite to same email after 60 seconds
        $this->travel(61)->seconds(function () use ($parameters) {
            $this->sendInviteResponse($parameters)->assertOk();
        });
    }

    #[Test]
    public function willReturnForbiddenWhenFolderHas_1000_collaborators(): void
    {
        [$folderOwner, $collaborator, $invitee] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        Folder::retrieved(function (Folder $folder) {
            $folder->collaboratorsCount = 1000;
        });

        Passport::actingAs($folderOwner);
        $this->sendInviteResponse($params = [
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertExactJson($expectation = ['message' => 'MaxCollaboratorsLimitReached']);

        Passport::actingAs($collaborator);
        $this->sendInviteResponse($params)
            ->assertForbidden()
            ->assertExactJson($expectation);
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

            $mail->assertSeeInHtml($user->full_name);
            $mail->assertSeeInHtml($folder->name);
            $mail->assertSeeInHtml("https://laravel.com/docs/9.x/validation?invite_hash={$inviteToken}");

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

        $this->sendInviteResponse(['email' => $secondaryEmail, 'folder_id' => $folder->id])->assertOk();
    }

    public function testWillReturnForbiddenWhenSendingInviteToSelf(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $folderOwnerSecondaryEmail = EmailFactory::new()->for($folderOwner)->create();
        $collaboratorSecondaryEmail = EmailFactory::new()->for($collaborator)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        $this->loginUser($folderOwner);
        $this->sendInviteResponse(['email' => $folderOwner->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertExactJson($error = ['message' => 'CannotSendInviteToSelf']);

        $this->sendInviteResponse(['email' => $folderOwnerSecondaryEmail->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertExactJson($error);


        $this->loginUser($collaborator);
        $this->sendInviteResponse(['email' => $collaborator->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertExactJson($error);

        $this->sendInviteResponse(['email' => $collaboratorSecondaryEmail->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertExactJson($error);
    }

    #[Test]
    public function willReturnForbiddenWhenFolderIsPrivate(): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($user)->private()->create();

        Passport::actingAs($user);
        $this->sendInviteResponse(['email' => $invitee->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertExactJson(['message' => 'CannotAddCollaboratorsToPrivateFolder']);
    }

    #[Test]
    public function willReturnForbiddenWhenFolderIsPasswordProtected(): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($user)->passwordProtected()->create();

        Passport::actingAs($user);
        $this->sendInviteResponse(['email' => $invitee->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertExactJson(['message' => 'CannotAddCollaboratorsToPasswordProtectedFolder']);
    }

    public function testWillReturnOkWhenInviterIsACollaborator(): void
    {
        [$folderOwner, $invitee, $collaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        $this->loginUser($collaborator);
        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folder->id
        ])->assertOk();
    }

    public function testWillReturnForbiddenWhenCollaboratorDoesNotHaveSendInvitePermission(): void
    {
        [$folderOwner, $collaborator, $invitee] = UserFactory::new()->count(3)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $permissions = UAC::all()
            ->toCollection()
            ->reject(Permission::INVITE_USER->value)
            ->all();

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);

        $this->loginUser($collaborator);
        $this->sendInviteResponse($query = ['email' => $invitee->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertExactJson($expectation = ['message' => 'NoSendInvitePermission']);

        //Assert will return same response when invite user action is disabled
        $this->toggleFolderCollaborationRestriction->update($folder->id, Permission::INVITE_USER, false);
        $this->sendInviteResponse($query)->assertForbidden()->assertExactJson($expectation);
    }

    public function testWillReturnConflictWhenCollaboratorIsSendingInviteToFolderOwner(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $folderOwnerSecondaryEmail = EmailFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        Passport::actingAs($collaborator);
        $this->sendInviteResponse(['email' => $folderOwner->email, 'folder_id' => $folder->id])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($error = ['message' => 'UserAlreadyACollaborator']);

        $this->sendInviteResponse(['email' => $folderOwnerSecondaryEmail->email, 'folder_id' => $folder->id])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($error);
    }

    public function testWillReturnForbiddenWhenCollaboratorIsSendingInviteWithPermissions(): void
    {
        [$collaborator, $invitee, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        Passport::actingAs($collaborator);
        $this->sendInviteResponse($query = [
            'email'       => $invitee->email,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson($expectation = ['message' => 'CollaboratorCannotSendInviteWithPermissions']);

        //Assert will return same response when invite user action is disabled
        $this->toggleFolderCollaborationRestriction->update($folder->id, Permission::INVITE_USER, false);
        $this->sendInviteResponse($query)->assertForbidden()->assertExactJson($expectation);
    }

    public function testSendInviteWithPermissions(): void
    {
        $this->assertCanSendInviteWithPermissions(['*']);
        $this->assertCanSendInviteWithPermissions(['addBookmarks']);
        $this->assertCanSendInviteWithPermissions(['removeBookmarks']);
        $this->assertCanSendInviteWithPermissions(['inviteUser']);
        $this->assertCanSendInviteWithPermissions(['updateFolder']);
        $this->assertCanSendInviteWithPermissions(['addBookmarks', 'removeBookmarks']);
        $this->assertCanSendInviteWithPermissions(['addBookmarks', 'removeBookmarks', 'inviteUser']);
    }

    private function assertCanSendInviteWithPermissions(array $permissions): void
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

        $this->sendInviteResponse($parameters)->assertOk();
    }

    public function testCollaborator_cannot_send_invites_when_folder_owner_has_deleted_account(): void
    {
        [$collaborator, $invitee, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

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

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

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

    #[Test]
    public function willReturnCorrectResponseWhenActionsIsDisabled(): void
    {
        [$collaborator, $folderOwner, $invitee, $otherInvitee] = UserFactory::new()->count(4)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        //Assert collaborator can update when disabled action is not invite user action
        $this->toggleFolderCollaborationRestriction->update($folder->id, Permission::UPDATE_FOLDER, false);
        $this->loginUser($collaborator);
        $this->sendInviteResponse(['email' => $invitee->email, 'folder_id' => $folder->id])->assertOk();

        $this->toggleFolderCollaborationRestriction->update($folder->id, Permission::INVITE_USER, false);

        $this->sendInviteResponse($query = ['email' => $otherInvitee->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertExactJson(['message' => 'InviteUserActionDisabled']);

        //when user is not a collaborator
        $this->loginUser(UserFactory::new()->create());
        $this->sendInviteResponse($query)->assertNotFound();


        $this->loginUser($folderOwner);
        $this->sendInviteResponse($query)->assertOk();
    }
}
