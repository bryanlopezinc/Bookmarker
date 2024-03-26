<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\ToggleFolderFeature;
use App\Cache\FolderInviteDataRepository;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\Enums\{Feature, Permission};
use Tests\TestCase;
use Illuminate\Support\Str;
use App\Models\SecondaryEmail;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use App\Mail\FolderCollaborationInviteMail;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\BannedCollaborator;
use App\Models\Folder;
use App\Models\FolderRole;
use App\UAC;
use Database\Factories\EmailFactory;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\InteractsWithValues;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;

class SendInviteTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;
    use InteractsWithValues;

    protected ToggleFolderFeature $toggleFolderCollaborationRestriction;
    protected FolderInviteDataRepository $invitesRepository;

    protected function shouldBeInteractedWith(): mixed
    {
        return UAC::validExternalIdentifiers();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->toggleFolderCollaborationRestriction = app(ToggleFolderFeature::class);
        $this->invitesRepository = app(FolderInviteDataRepository::class);
    }

    protected function tearDown(): void
    {
        Str::createUuidsNormally();

        parent::tearDown();
    }

    protected function sendInviteResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(
            route('sendFolderCollaborationInvite', ['folder_id' => $parameters['folder_id']]),
            Arr::except($parameters, ['folder_id'])
        );
    }

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/invite', 'sendFolderCollaborationInvite');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->sendInviteResponse(['folder_id' => 4, 'permissions' => 'inviteUsers,addBookmarks'])->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->make(['id' => 40]));

        $this->sendInviteResponse(['folder_id' => 2])
            ->assertUnprocessable()
            ->assertJsonMissingValidationErrors(['permissions'])
            ->assertJsonValidationErrors(['email' => ['The email field is required']]);

        $this->sendInviteResponse(['email' => 'foo', 'folder_id' => 29])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => ['The email must be a valid email address.']]);

        $this->sendInviteResponse(['permissions' => ['bar', 'foo'], 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => ['The permissions must be a string.']]);

        $this->sendInviteResponse(['permissions' => 'view,foo', 'folder_id' => 44])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => ['The selected permissions is invalid.']]);

        $this->sendInviteResponse(['permissions' => 'addBookmarks,addBookmarks', 'folder_id' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions.0' => ['The permissions.0 field has a duplicate value.'],
                'permissions.1' => ['The permissions.1 field has a duplicate value.'],
            ]);

        $this->sendInviteResponse(['roles' => 'foo,foo', 'folder_id' => 12])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['roles.0' => ['The roles.0 field has a duplicate value.']]);

        $this->sendInviteResponse(['roles' => str_repeat('F', 65), 'folder_id' => 32])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['roles.0' => ['The roles.0 must not be greater than 64 characters.']]);

        $this->sendInviteResponse(['roles' => implode(',', $this->faker->unique()->words(11)), 'folder_id' => 43])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['roles' => ['The roles must not have more than 10 items.']]);

        $this->sendInviteResponse([
            'email'       => $this->faker->unique()->email,
            'folder_id'   => 44,
            'permissions' => '*,addBookmarks',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'permissions' => ['The permissions field cannot contain any other value with the * wildcard.'],
        ]);
    }

    #[Test]
    public function sendInvite(): void
    {
        $inviteToken = $this->faker->uuid;
        Str::createUuidsUsing(fn () => $inviteToken);

        config(['settings.ACCEPT_INVITE_URL' => 'https://laravel.com/docs/9.x/validation?invite_hash=:invite_hash']);

        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $mailer = Mail::getFacadeRoot();
        $folder = FolderFactory::new()->for($user)->create();

        $this->loginUser($user);
        Mail::fake();

        $this->sendInviteResponse(['email' => $invitee->email, 'folder_id' => $folder->id])->assertOk();

        Mail::assertQueued(function (FolderCollaborationInviteMail $mail) use ($invitee, $user, $mailer, $folder, $inviteToken) {
            $this->assertSame($invitee->email, $mail->to[0]['address']);

            /** @see https://github.com/laravel/framework/issues/24005#issuecomment-989629711 */
            Mail::swap($mailer);

            $mail->assertSeeInHtml($user->full_name->present());
            $mail->assertSeeInHtml($folder->name->present());
            $mail->assertSeeInHtml("https://laravel.com/docs/9.x/validation?invite_hash={$inviteToken}");

            return true;
        });

        $invitationData = $this->invitesRepository->get($inviteToken);

        $this->assertEquals($invitee->id, $invitationData->inviteeId);
        $this->assertEquals($folder->id, $invitationData->folderId);
        $this->assertEquals($user->id, $invitationData->inviterId);
        $this->assertEquals([], $invitationData->permissions->toArray());
        $this->assertEquals([], $invitationData->roles);
    }

    #[Test]
    public function collaboratorWithPermissionCanSendInvite(): void
    {
        [$folderOwner, $invitee, $collaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        $this->loginUser($collaborator);
        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertOk();
    }

    #[Test]
    public function collaboratorWithRoleCanSendInvite(): void
    {
        [$folderOwner, $invitee, $collaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);
        $this->attachRoleToUser($collaborator, $this->createRole('creator', $folder, [Permission::INVITE_USER, Permission::DELETE_BOOKMARKS]));

        $this->loginUser($collaborator);
        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertOk();
    }

    public function testSendInviteToSecondaryEmail(): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->loginUser($user);

        SecondaryEmail::query()->create([
            'email'       => $secondaryEmail = $this->faker->unique()->email,
            'user_id'     => $invitee->id,
            'verified_at'  => now()
        ]);

        $this->sendInviteResponse(['email' => $secondaryEmail, 'folder_id' => $folder->id])->assertOk();
    }

    public function testWillReturnNotFoundWhenInviteeIsNotARegisteredUser(): void
    {
        $inviteToken = $this->faker->uuid;

        Str::createUuidsUsing(fn () => $inviteToken);

        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->sendInviteResponse(['email' => $this->faker->unique()->email, 'folder_id' => $folder->id])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'InviteeNotFound']);

        $this->assertInvitationDataNotSaved($inviteToken);
    }

    private function assertInvitationDataNotSaved(string $inviteId): void
    {
        $this->assertFalse($this->invitesRepository->has($inviteId));
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $inviteToken = $this->faker->uuid;

        Str::createUuidsUsing(fn () => $inviteToken);

        $this->loginUser(UserFactory::new()->create());

        $this->sendInviteResponse([
            'folder_id' => FolderFactory::new()->create()->id + 1,
            'email'     => UserFactory::new()->create()->email,
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertInvitationDataNotSaved($inviteToken);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $inviteToken = $this->faker->uuid;

        Str::createUuidsUsing(fn () => $inviteToken);

        $this->loginUser(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->sendInviteResponse([
            'folder_id' => $folder->id,
            'email'     => UserFactory::new()->create()->email,
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertInvitationDataNotSaved($inviteToken);
    }

    public function testWillReturnConflictWhenUserIsAlreadyACollaborator(): void
    {
        [$folderOwner, $invitee, $collaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $inviteeSecondaryEmail = EmailFactory::new()->for($invitee)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);
        $this->CreateCollaborationRecord($invitee, $folder);

        $this->loginUser($folderOwner);
        $this->sendInviteResponse(['email' => $invitee->email, 'folder_id' => $folder->id])
            ->assertConflict()
            ->assertJsonFragment($error = ['message' => 'UserAlreadyACollaborator']);

        $this->sendInviteResponse(['email' => $inviteeSecondaryEmail->email, 'folder_id' => $folder->id])
            ->assertConflict()
            ->assertJsonFragment($error);

        $this->loginUser($collaborator);
        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertConflict()->assertJsonFragment($error);

        $this->sendInviteResponse([
            'email'     => $inviteeSecondaryEmail->email,
            'folder_id' => $folder->id,
        ])->assertConflict()->assertJsonFragment($error);
    }

    public function testCanOnlySendOneInvitePerMinuteToSameEmail(): void
    {
        [$folderOwner, $invitee, $secondInvitee] = UserFactory::new()->count(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        SecondaryEmail::query()->create([
            'email'       => $inviteeSecondaryEmail = $this->faker->unique()->email,
            'user_id'     => $invitee->id,
            'verified_at'  => now()
        ]);

        $this->loginUser($folderOwner);
        $this->sendInviteResponse($parameters = ['email' => $invitee->email, 'folder_id' => $folder->id])->assertOk();

        $this->sendInviteResponse($parameters)
            ->assertTooManyRequests()
            ->assertHeader('resend-invite-after')
            ->tap(function (TestResponse $response) {
                $this->assertLessThanOrEqual($response->baseResponse->headers->get('resend-invite-after'), 59);
            });

        //Assert will not rate limit user when sending invite to invitee secondary email
        $this->sendInviteResponse(['email' => $inviteeSecondaryEmail, 'folder_id' => $folder->id])->assertOk();

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
        [$inviteToken, $secondInviteToken] = [$this->faker->uuid, $this->faker->uuid];

        Str::createUuidsUsingSequence([$inviteToken, $secondInviteToken]);

        [$folderOwner, $collaborator, $invitee] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        Folder::retrieved(function (Folder $folder) {
            $folder->collaborators_count = 1000;
        });

        $this->loginUser($folderOwner);
        $this->sendInviteResponse($params = [
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()
            ->assertJsonFragment($expectation = ['message' => 'MaxCollaboratorsLimitReached']);

        $this->loginUser($collaborator);
        $this->sendInviteResponse($params)
            ->assertForbidden()
            ->assertJsonFragment($expectation);

        $this->assertInvitationDataNotSaved($inviteToken);
        $this->assertInvitationDataNotSaved($secondInviteToken);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorsLimitSetByFolderOwnerIsExceeded(): void
    {
        $inviteToken = $this->faker->uuid;

        Str::createUuidsUsing(fn () => $inviteToken);

        [$folderOwner, $collaborator, $invitee] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->setMaxCollaboratorsLimit(10))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        for ($i = 0; $i < 10; $i++) {
            $this->CreateCollaborationRecord(UserFactory::new()->create(), $folder);
        }

        $this->loginUser($folderOwner);
        $this->sendInviteResponse($params = [
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()->assertExactJson($expectation = [
            'message' => 'MaxFolderCollaboratorsLimitReached',
            'info' => 'The Folder has reached its max collaborators limit set by the folder owner.'
        ]);

        $this->loginUser($collaborator);
        $this->sendInviteResponse($params)
            ->assertForbidden()
            ->assertExactJson($expectation);

        $this->assertInvitationDataNotSaved($inviteToken);
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
            ->assertJsonFragment($error = ['message' => 'CannotSendInviteToSelf']);

        $this->sendInviteResponse(['email' => $folderOwnerSecondaryEmail->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment($error);

        $this->loginUser($collaborator);
        $this->sendInviteResponse(['email' => $collaborator->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment($error);

        $this->sendInviteResponse(['email' => $collaboratorSecondaryEmail->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment($error);
    }

    #[Test]
    public function willReturnForbiddenWhenFolderIsPrivate(): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($user)->private()->create();

        $this->loginUser($user);
        $this->sendInviteResponse(['email' => $invitee->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderIsMarkedAsPrivate']);
    }

    #[Test]
    public function willReturnForbiddenWhenFolderIsPasswordProtected(): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($user)->passwordProtected()->create();

        $this->loginUser($user);
        $this->sendInviteResponse(['email' => $invitee->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderIsMarkedAsPrivate']);
    }

    public function testWillReturnForbiddenWhenCollaboratorDoesNotHaveSendInvitePermissionOrRole(): void
    {
        [$folderOwner, $collaborator, $invitee] = UserFactory::new()->count(3)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $permissions = UAC::all()
            ->toCollection()
            ->reject(Permission::INVITE_USER->value)
            ->all();

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: FolderFactory::new()->create(), permissions: Permission::ADD_BOOKMARKS));

        $this->loginUser($collaborator);
        $this->sendInviteResponse($query = ['email' => $invitee->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment($expectation = ['message' => 'PermissionDenied']);

        //Assert will return same response when invite user action is disabled
        $this->toggleFolderCollaborationRestriction->disable($folder->id, Feature::SEND_INVITES);
        $this->sendInviteResponse($query)->assertForbidden()->assertJsonFragment($expectation);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorRoleNoLongerExists(): void
    {
        [$folderOwner, $collaborator, $invitee] = UserFactory::new()->count(3)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->attachRoleToUser($collaborator, $role = $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: FolderFactory::new()->create(), permissions: Permission::ADD_BOOKMARKS));

        $role->delete();

        $this->loginUser($collaborator);
        $this->sendInviteResponse(['email' => $invitee->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'PermissionDenied']);
    }

    public function testWillReturnConflictWhenCollaboratorIsSendingInviteToFolderOwner(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $folderOwnerSecondaryEmail = EmailFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        $this->loginUser($collaborator);
        $this->sendInviteResponse(['email' => $folderOwner->email, 'folder_id' => $folder->id])
            ->assertConflict()
            ->assertJsonFragment($error = ['message' => 'UserAlreadyACollaborator']);

        $this->sendInviteResponse(['email' => $folderOwnerSecondaryEmail->email, 'folder_id' => $folder->id])
            ->assertConflict()
            ->assertJsonFragment($error);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsSendingInviteWithPermissionsOrRoles(): void
    {
        [$collaborator, $invitee, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        $this->loginUser($collaborator);
        $this->sendInviteResponse($query = [
            'email'       => $invitee->email,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks',
        ])->assertBadRequest()
            ->assertJsonFragment($expectation = ['message' => 'CollaboratorCannotSendInviteWithPermissionsOrRoles']);

        //Assert will return same response when invite user action is disabled
        $this->toggleFolderCollaborationRestriction->disable($folder->id, Feature::SEND_INVITES);
        $this->sendInviteResponse($query)->assertBadRequest()->assertJsonFragment($expectation);
    }

    public function testSendInviteWithPermissions(): void
    {
        $this->assertCanSendInviteWithPermissions(['*']);
        $this->assertCanSendInviteWithPermissions(['addBookmarks']);
        $this->assertCanSendInviteWithPermissions(['removeBookmarks']);
        $this->assertCanSendInviteWithPermissions(['inviteUsers']);
        $this->assertCanSendInviteWithPermissions(['updateFolder']);
        $this->assertCanSendInviteWithPermissions(['removeUser']);
        $this->assertCanSendInviteWithPermissions(['addBookmarks', 'removeBookmarks']);
        $this->assertCanSendInviteWithPermissions(['addBookmarks', 'removeBookmarks', 'inviteUsers']);
    }

    private function assertCanSendInviteWithPermissions(array $permissions): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $this->loginUser($user);

        $folder = FolderFactory::new()->for($user)->create();

        $parameters = ['email' => $invitee->email, 'folder_id' => $folder->id];

        if ( ! empty($permissions)) {
            $parameters['permissions'] = implode(',', $permissions);
        }

        $this->sendInviteResponse($parameters)->assertOk();
    }

    #[Test]
    public function sendInviteWithRolesOnly(): void
    {
        $inviteToken = $this->faker->uuid;
        Str::createUuidsUsing(fn () => $inviteToken);

        [$invitee, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folder->roles()->save(new FolderRole(['name' => $firstRoleName = $this->faker->word]));
        $folder->roles()->save(new FolderRole(['name' => $secondRoleName = $this->faker->word]));

        $this->loginUser($folderOwner);
        $this->sendInviteResponse([
            'folder_id' => $folder->id,
            'email'     => $invitee->email,
            'roles'     => implode(',', $roleNames = [$firstRoleName, $secondRoleName])
        ])->assertOk();

        $invitationData = $this->invitesRepository->get($inviteToken);

        $this->assertEquals($roleNames, $invitationData->roles);
        $this->assertEquals([], $invitationData->permissions->toArray());
    }

    #[Test]
    public function sendInviteWithRolesAndPermissions(): void
    {
        $inviteToken = $this->faker->uuid;
        Str::createUuidsUsing(fn () => $inviteToken);

        [$invitee, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folder->roles()->save(new FolderRole(['name' => $firstRoleName = $this->faker->word]));
        $folder->roles()->save(new FolderRole(['name' => $secondRoleName = $this->faker->word]));

        $this->loginUser($folderOwner);
        $this->sendInviteResponse([
            'folder_id'   => $folder->id,
            'email'       => $invitee->email,
            'roles'       => implode(',', $roleNames = [$firstRoleName, $secondRoleName]),
            'permissions' => 'addBookmarks'
        ])->assertOk();

        $invitationData = $this->invitesRepository->get($inviteToken);

        $this->assertEquals($roleNames, $invitationData->roles);
        $this->assertEquals([Permission::ADD_BOOKMARKS->value], $invitationData->permissions->toArray());
    }

    #[Test]
    public function cannotSendInviteWithInvalidRoles(): void
    {
        $inviteToken = $this->faker->uuid;
        Str::createUuidsUsing(fn () => $inviteToken);

        [$invitee, $loggedInUser, $otherUser] = UserFactory::times(3)->create();

        [$loggedInUserFolder, $loggedInUserSecondFolder] = FolderFactory::times(2)->for($loggedInUser)->create();
        $otherUserFolder = FolderFactory::new()->for($otherUser)->create();

        $otherUserFolder->roles()->save(new FolderRole(['name' => $otherUserFolderRoleName = $this->faker->word]));
        $loggedInUserSecondFolder->roles()->save(new FolderRole(['name' => $roleName = $this->faker->word]));

        $this->loginUser($loggedInUser);
        $this->sendInviteResponse([
            'folder_id' => $loggedInUserFolder->id,
            'email'     => $invitee->email,
            'roles'     => $otherUserFolderRoleName
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->sendInviteResponse([
            'folder_id' => $loggedInUserFolder->id,
            'email'     => $invitee->email,
            'roles'     => $roleName
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->assertInvitationDataNotSaved($inviteToken);
    }

    public function testCollaborator_cannot_send_invites_when_folder_owner_has_deleted_account(): void
    {
        [$collaborator, $invitee, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        $folderOwner->delete();

        $this->loginUser($collaborator);
        $this->sendInviteResponse([
            'email'     => $invitee->email,
            'folder_id' => $folder->id,
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
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

        $this->loginUser($collaborator);
        $this->sendInviteResponse([
            'email'     => $bannedCollaborator->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()->assertJsonFragment($error = ['message' => 'UserBanned']);

        $this->sendInviteResponse([
            'email'     => $bannedCollaboratorSecondEmail->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()->assertJsonFragment($error);

        $this->loginUser($folderOwner);
        $this->sendInviteResponse([
            'email'     => $bannedCollaborator->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()->assertJsonFragment($error = ['message' => 'UserBanned']);

        $this->sendInviteResponse([
            'email'     => $bannedCollaboratorSecondEmail->email,
            'folder_id' => $folder->id,
        ])->assertForbidden()->assertJsonFragment($error);
    }

    #[Test]
    public function willReturnCorrectResponseWhenFeatureIsDisabled(): void
    {
        [$collaborator, $folderOwner, $invitee, $otherInvitee] = UserFactory::new()->count(4)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        //Assert collaborator can update when disabled action is not invite user action
        $this->toggleFolderCollaborationRestriction->disable($folder->id, Feature::UPDATE_FOLDER);
        $this->loginUser($collaborator);
        $this->sendInviteResponse(['email' => $invitee->email, 'folder_id' => $folder->id])->assertOk();

        $this->toggleFolderCollaborationRestriction->disable($folder->id, Feature::SEND_INVITES);

        $this->sendInviteResponse($query = ['email' => $otherInvitee->email, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);

        //when user is not a collaborator
        $this->loginUser(UserFactory::new()->create());
        $this->sendInviteResponse($query)->assertNotFound();

        $this->loginUser($folderOwner);
        $this->sendInviteResponse($query)->assertOk();
    }
}
