<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Mail\FolderCollaborationInviteMail;
use App\Models\FolderAccess;
use App\Models\FolderPermission as Permission;
use App\ValueObjects\Url;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AcceptFolderCollaborationInviteTest extends TestCase
{
    use WithFaker;

    protected function acceptInviteResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('acceptFolderCollaborationInvite', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders/invite/accept', 'acceptFolderCollaborationInvite');
    }

    public function testUnAuthorizedUserCanAccessRoute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        //should be UnAuthorized if route is protected but returns
        // a forbidden response because we provided an invalid url signature.
        $this->acceptInviteResponse()->assertForbidden();
    }

    public function testAuthorizedUserCanAccessRoute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->acceptInviteResponse()->assertForbidden();
    }

    public function testUnAuthorizedClientCannotAccessRoute(): void
    {
        $this->acceptInviteResponse()->assertUnauthorized();
    }

    public function testSignatureMustBeValid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $parameters = $this->extractSignedUrlParameters(function () use ($invitee, $folder, $user) {
            Passport::actingAs($user);

            $this->getJson(route('sendFolderCollaborationInvite', [
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ]))->assertOk();
        });

        $parameters['invite_hash'] = Crypt::encrypt('some-data');

        $this->acceptInviteResponse($parameters)
            ->assertForbidden()
            ->assertSee('Invalid signature.');

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folder->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function testWillNotCreatePermissionWhenInvitationHasExpired(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $parameters = $this->extractSignedUrlParameters(function () use ($invitee, $folder, $user) {
            Passport::actingAs($user);

            $this->getJson(route('sendFolderCollaborationInvite', [
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ]))->assertOk();
        });

        $this->travel(25)->hours(function () use ($parameters) {
            $this->acceptInviteResponse($parameters)
                ->assertForbidden()
                ->assertSee('Invalid signature.');
        });

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folder->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function testWillAcceptInviteWithPermissions(): void
    {
        //Will give only view-bookmarks permission if no permissions were specified
        $this->assertWillAcceptInvite([], function (Collection $savedPermissionTypes) {
            $this->assertCount(1, $savedPermissionTypes);
            $this->assertTrue($savedPermissionTypes->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['addBookmarks'], function (Collection $savedPermissionTypes) {
            $this->assertCount(2, $savedPermissionTypes);
            $this->assertTrue($savedPermissionTypes->containsStrict(Permission::ADD_BOOKMARKS));
            $this->assertTrue($savedPermissionTypes->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['removeBookmarks'], function (Collection $savedPermissionTypes) {
            $this->assertCount(2, $savedPermissionTypes);
            $this->assertTrue($savedPermissionTypes->containsStrict(Permission::DELETE_BOOKMARKS));
            $this->assertTrue($savedPermissionTypes->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['inviteUser'], function (Collection $savedPermissionTypes) {
            $this->assertCount(2, $savedPermissionTypes);
            $this->assertTrue($savedPermissionTypes->containsStrict(Permission::INVITE));
            $this->assertTrue($savedPermissionTypes->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['removeBookmarks', 'addBookmarks'], function (Collection $savedPermissionTypes) {
            $this->assertCount(3, $savedPermissionTypes);
            $this->assertTrue($savedPermissionTypes->containsStrict(Permission::DELETE_BOOKMARKS));
            $this->assertTrue($savedPermissionTypes->containsStrict(Permission::ADD_BOOKMARKS));
            $this->assertTrue($savedPermissionTypes->containsStrict(Permission::VIEW_BOOKMARKS));
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

        $parameters = $this->extractSignedUrlParameters(function () use ($user, $parameters) {
            Passport::actingAs($user);
            $this->getJson(route('sendFolderCollaborationInvite', $parameters))->assertOk();
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

        $parameters = $this->extractSignedUrlParameters(function () use ($invitee, $folder, $user) {
            Passport::actingAs($user);

            $this->getJson(route('sendFolderCollaborationInvite', [
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ]))->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        $this->acceptInviteResponse($parameters)
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

        $parameters = $this->extractSignedUrlParameters(function () use ($invitee, $folder) {
            $this->getJson(route('sendFolderCollaborationInvite', [
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ]))->assertOk();
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

        $parameters = $this->extractSignedUrlParameters(function () use ($invitee, $folder, $user) {
            Passport::actingAs($user);

            $this->getJson(route('sendFolderCollaborationInvite', [
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ]))->assertOk();
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

        $parameters = $this->extractSignedUrlParameters(function () use ($invitee, $folder) {
            $this->getJson(route('sendFolderCollaborationInvite', [
                'email' => $invitee->email,
                'folder_id' => $folder->id,
            ]))->assertOk();
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

    private function extractSignedUrlParameters(\Closure $action): array
    {
        config([
            'settings.ACCEPT_INVITE_URL' => $this->faker->url . '?invite_hash=:invite_hash&signature=:signature&expires=:expires'
        ]);

        $parameters = [];
        Mail::fake();
        $action();

        Mail::assertQueued(function (FolderCollaborationInviteMail $mail) use (&$parameters) {
            $parts = (new Url($mail->inviteUrl()))->parseQuery();
            $parameters['expires'] = $parts['expires'];
            $parameters['signature'] = $parts['signature'];
            $parameters['invite_hash'] = $parts['invite_hash'];

            return true;
        });

        return $parameters;
    }
}
