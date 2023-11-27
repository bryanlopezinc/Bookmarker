<?php

namespace Tests\Feature\User;

use App\Enums\TwoFaMode;
use App\Filesystem\ProfileImageFileSystem;
use App\Models\SecondaryEmail;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\ValueObjects\Url;
use App\ValueObjects\Username;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use WithFaker;

    private static string $verificationUrl;
    private static User $createdUser;

    protected function createUserResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createUser'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users', 'createUser');
    }

    public function testUnauthorizedClientCannotAccessRoute(): void
    {
        $this->createUserResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenAttributesAreInvalid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->createUserResponse()
            ->assertUnprocessable()
            ->assertJsonCount(5, 'errors')
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'username',
                'email',
                'password'
            ]);

        $this->createUserResponse(['last_name' => str_repeat('A', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name' => 'The last name must not be greater than 100 characters.']);

        $this->createUserResponse(['first_name' => str_repeat('A', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name' => 'The first name must not be greater than 100 characters.']);

        $this->createUserResponse(['password' => 'secured'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password must be at least 8 characters.']);

        $this->createUserResponse(['password' => 'password_password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password must contain at least one number.']);

        $this->createUserResponse(['profile_photo' => UploadedFile::fake()->image('myPicture.txt')->size(10)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_photo' => 'The profile photo must be an image.']);

        $this->createUserResponse(['profile_photo' => UploadedFile::fake()->image('myPicture.jpg')->size(2000)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_photo' => 'The profile photo must not be greater than 1000 kilobytes.']);
    }

    public function testWillReturnUnprocessableWhenUsernameIsValid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->createUserResponse(['username' => UserFactory::new()->create()->username])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'The username has already been taken.']);

        $this->createUserResponse(['username' => Str::random(Username::MAX_LENGTH + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'The username must not be greater than 15 characters.']);

        $this->createUserResponse(['username' => Str::random(Username::MIN_LENGTH - 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'The username must be at least 8 characters.']);

        $this->createUserResponse(['username' => Str::random(Username::MIN_LENGTH - 1) . '!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'The username contains invalid characters']);
    }

    public function testWillReturnUnprocessableWhenEmailExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->createUserResponse(['email' => UserFactory::new()->create()->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function testWillReturnUnprocessableWhenEmailIsExistingSecondaryEmail(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $user = UserFactory::new()->create();

        SecondaryEmail::query()->create([
            'email'       => $userSecondaryEmail = UserFactory::new()->make()->email,
            'user_id'     => $user->id,
            'verified_at' => now()
        ]);

        $this->createUserResponse(['email' => $userSecondaryEmail])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function testCreateUser(): void
    {
        Passport::actingAsClient($client = ClientFactory::new()->asPasswordClient()->create());

        config(['settings.EMAIL_VERIFICATION_URL' => 'https://laravel.com/:id?hash=:hash&signature=:signature&expires=:expires&t=f']);

        Notification::fake();

        $this->createUserResponse([
            'first_name'            => $firstName = $this->faker->firstName,
            'last_name'             => $lastName =  $this->faker->lastName,
            'username'              => $username = Str::random(Username::MAX_LENGTH - 2) . '_' . rand(0, 9),
            'email'                 => $mail = $this->faker->safeEmail,
            'password'              => $password = str::random(7) . rand(0, 9),
            'password_confirmation' => $password,
            'client_id'             => $client->id,
            'client_secret'         => $client->secret,
            'grant_type'            => 'password',
        ])->assertCreated();

        /** @var User */
        $user = User::query()->where('email', $mail)->sole();
        self::$createdUser = $user;

        $this->assertEquals($username, $user->username);
        $this->assertEquals($firstName, $user->first_name);
        $this->assertEquals($lastName, $user->last_name);
        $this->assertEquals("{$firstName} {$lastName}", $user->full_name);
        $this->assertEquals(TwoFaMode::NONE, $user->two_fa_mode);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNull($user->profile_image_path);

        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
            static::$verificationUrl = $url = $notification->toMail($user)->actionUrl;

            $parts = (new Url($url))->parseQuery();

            $this->assertEquals(
                $url,
                "https://laravel.com/{$user->id}?hash={$parts['hash']}&signature={$parts['signature']}&expires={$parts['expires']}&t=f"
            );

            return true;
        });
    }

    /**
     * @depends testCreateUser
     */
    public function testCanVerifyEmailWithParameters(): void
    {
        Passport::actingAs($user = self::$createdUser);

        $components = (new Url(static::$verificationUrl))->parseQuery();

        $uri = route('verification.verify', [
            $user->id,
            $components['hash'],
            'signature' => $components['signature'],
            'expires' => $components['expires']
        ]);

        $this->getJson($uri)->assertOk();

        $this->assertTrue(User::whereKey($user->id)->sole()->email_verified_at->isToday());
    }

    #[Test]
    public function wilProfileImage(): void
    {
        $filesystem = new ProfileImageFileSystem;

        Passport::actingAsClient($client = ClientFactory::new()->asPasswordClient()->create());

        $this->createUserResponse([
            'first_name'            => $this->faker->firstName,
            'last_name'             => $this->faker->lastName,
            'username'              =>  Str::random(Username::MAX_LENGTH - 2) . '_' . rand(0, 9),
            'email'                 => $email = $this->faker->safeEmail,
            'password'              => $password = str::random(7) . rand(0, 9),
            'password_confirmation' => $password,
            'client_id'             => $client->id,
            'client_secret'         => $client->secret,
            'grant_type'            => 'password',
            'profile_photo'         => UploadedFile::fake()->image('myPicture.jpg')->size(1000)
        ])->assertCreated();

        /** @var User */
        $user = User::query()->where('email', $email)->sole();

        $this->assertEquals(strlen($user->profile_image_path), 44);
        $this->assertStringEndsWith('.jpg', $user->profile_image_path);
        $this->assertTrue($filesystem->exists($user->profile_image_path));
    }
}
