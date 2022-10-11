<?php

namespace Tests\Feature\Folder;

use App\Mail\FolderCollaborationInviteMail;
use App\Models\FolderAccess;
use App\Models\FolderPermission;
use App\Models\SecondaryEmail;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SendFolderCollaborationInviteTest extends TestCase
{
    use WithFaker;

    protected function sendInviteResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('sendFolderCollaborationInvite', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders/invite', 'sendFolderCollaborationInvite');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->sendInviteResponse()->assertUnauthorized();
    }

    public function testRequiredAttrbutesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->sendInviteResponse()->assertJsonValidationErrors([
            'email' => ['The email field is required'],
            'permissions' => ['The permissions field is required'],
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
            'permissions' => 'viewBookmarks,viewBookmarks'
        ])->assertJsonValidationErrors([
            'permissions.0' => ['The permissions.0 field has a duplicate value.'],
            'permissions.1' => ['The permissions.1 field has a duplicate value.'],
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
            'permissions' => 'viewBookmarks',
            'folder_id' => $folder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'User not found']);
    }

    public function testFolderMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->sendInviteResponse([
            'folder_id' => FolderFactory::new()->create()->id + 1,
            'permissions' => 'viewBookmarks',
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
            'permissions' => 'viewBookmarks',
            'email' => UserFactory::new()->create()->email,
        ])->assertForbidden();
    }

    public function testCannotSendInviteToExistingCollaborator(): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        Passport::actingAs($user);

        FolderAccess::query()->create([
            'folder_id' => $folder->id,
            'user_id' => $invitee->id,
            'permission_id' => FolderPermission::first()->id
        ]);

        $this->sendInviteResponse([
            'email' => $invitee->email,
            'folder_id' => $folder->id,
            'permissions' => 'viewBookmarks'
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
            'permissions' => 'viewBookmarks'
        ])->assertOk();

        //same user with same email
        $this->sendInviteResponse($parameters)->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
        $this->sendInviteResponse($parameters)->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        //same user different email
        $this->sendInviteResponse([
            'email' => $inviteeSecondaryEmail,
            'folder_id' => $folder->id,
            'permissions' => 'viewBookmarks'
        ])->assertOk();

        //different user
        $this->sendInviteResponse([
            'email' => $secondInvitee->email,
            'folder_id' => $folder->id,
            'permissions' => 'viewBookmarks'
        ])->assertOk();

        $this->travel(62)->seconds(function () use ($parameters) {
            $this->sendInviteResponse($parameters)->assertOk(); //same user with same email
        });
    }

    public function testCanSendInviteToPrimaryEmail(): void
    {
        [$user, $invitee] = UserFactory::new()->count(2)->create();
        $mailer = Mail::getFacadeRoot();

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        Passport::actingAs($user);
        Mail::fake();

        $this->sendInviteResponse([
            'email' => $invitee->email,
            'folder_id' => $folder->id,
            'permissions' => 'viewBookmarks'
        ])->assertOk();

        Mail::assertQueued(function (FolderCollaborationInviteMail $mail) use ($invitee, $user, $mailer, $folder) {
            $this->assertSame($invitee->email, $mail->to[0]['address']);

            /** @see https://github.com/laravel/framework/issues/24005#issuecomment-989629711 */
            Mail::swap($mailer);

            $mail->assertSeeInHtml($user->firstname);
            $mail->assertSeeInHtml($user->lastname);
            $mail->assertSeeInHtml($folder->name);

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
            'permissions' => 'viewBookmarks'
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
            'permissions' => 'viewBookmarks'
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
            'permissions' => 'viewBookmarks'
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Cannot send invite to self'
            ]);
    }
}
