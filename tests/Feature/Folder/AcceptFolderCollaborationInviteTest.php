<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Mail\FolderCollaborationInviteMail;
use App\Models\FolderAccess;
use App\Models\FolderPermission;
use App\ValueObjects\Url;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
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
                'permissions' => 'viewBookmarks'
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
                'permissions' => 'viewBookmarks'
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

    public function testAcceptInvite(): void
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
                'permissions' => 'viewBookmarks'
            ]))->assertOk();
        });

        $this->acceptInviteResponse($parameters)->assertCreated();

        $this->assertDatabaseHas(FolderAccess::class, [
            'folder_id' => $folder->id,
            'user_id' => $invitee->id,
            'permission_id' => FolderPermission::query()->where('name', FolderPermission::VIEW_BOOKMARKS)->sole()->id
        ]);
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
                'permissions' => 'viewBookmarks'
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
                'permissions' => 'viewBookmarks'
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
                'permissions' => 'viewBookmarks'
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

        $parameters = $this->extractSignedUrlParameters(function () use ($invitee, $folder, $user) {
            $this->getJson(route('sendFolderCollaborationInvite', [
                'email' => $invitee->email,
                'folder_id' => $folder->id,
                'permissions' => 'viewBookmarks'
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
