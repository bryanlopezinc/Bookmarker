<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\TwoFaMode;
use App\Filesystem\ProfileImageFileSystem;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use WithFaker;

    protected function updateProfileResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(route('updateUser'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users', 'updateUser');
    }

    #[Test]
    public function willReturnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->updateProfileResponse()->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenAttributesAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->updateProfileResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name' => 'The first name field is required when none of last name / two fa mode / password / profile photo are present.']);

        $this->updateProfileResponse(['first_name' => str_repeat('A', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name' => 'The first name must not be greater than 100 characters.']);

        $this->updateProfileResponse(['last_name' => str_repeat('A', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name' => 'The last name must not be greater than 100 characters.']);

        $this->updateProfileResponse(['two_fa_mode' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['two_fa_mode']);

        $this->updateProfileResponse([
            'password'              => $this->faker->password(8) . '1',
            'old_password'          => 'notMyPassword',
            'password_confirmation' => 'password'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['old_password' => 'The password is incorrect']);

        $this->updateProfileResponse(['profile_photo' => UploadedFile::fake()->image('myPicture.txt')->size(10)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_photo' => 'The profile photo must be an image.']);

        $this->updateProfileResponse(['profile_photo' => UploadedFile::fake()->image('myPicture.jpg')->size(2000)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_photo' => 'The profile photo must not be greater than 1000 kilobytes.']);
    }

    #[Test]
    public function updateFirstName(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->updateProfileResponse(['first_name' => $firstName = $this->faker->firstName])->assertOk();

        /** @var User */
        $user = User::query()->whereKey($user->id)->first();

        $this->assertEquals($firstName, $user->first_name);
        $this->assertEquals($user->full_name->value, "{$firstName} {$user->last_name}");
    }

    #[Test]
    public function updateLastName(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->updateProfileResponse(['last_name' => $lastName = $this->faker->lastName])->assertOk();

        /** @var User */
        $user = User::query()->whereKey($user->id)->first();

        $this->assertEquals($lastName, $user->last_name);
        $this->assertEquals($user->full_name->value, "{$user->first_name} {$lastName}");
    }

    #[Test]
    public function updateTwoFAMode(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->updateProfileResponse(['two_fa_mode' => 'none'])->assertOk();
        $user = User::query()->whereKey($user->id)->first();
        $this->assertEquals($user->two_fa_mode, TwoFaMode::NONE);

        $this->updateProfileResponse(['two_fa_mode' => 'email'])->assertOk();
        $user = User::query()->whereKey($user->id)->first();
        $this->assertEquals($user->two_fa_mode, TwoFaMode::EMAIL);
    }

    #[Test]
    public function updatePassword(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->updateProfileResponse([
            'password'              => $newPassword = $this->faker->password(8) . '1',
            'old_password'          => 'password',
            'password_confirmation' => $newPassword
        ])->assertOk();

        $user = User::query()->whereKey($user->id)->first();

        $this->assertTrue(Hash::check($newPassword, $user->password));
    }

    #[Test]
    public function updateProfileImage(): void
    {
        $filesystem = new ProfileImageFileSystem();
        $OtherUserProfileImage = $filesystem->store(UploadedFile::fake()->image('myPicture.jpg'));

        $this->loginUser($user = UserFactory::new()->create());
        $this->updateProfileResponse(['profile_photo' => UploadedFile::fake()->image('myPicture.jpg')->size(1000)])->assertOk();

        /** @var User */
        $userAfterFirstUpdate = User::query()->whereKey($user->id)->first();

        $this->assertEquals(strlen($userAfterFirstUpdate->profile_image_path), 44);
        $this->assertStringEndsWith('.jpg', $userAfterFirstUpdate->profile_image_path);
        $this->assertTrue($filesystem->exists($userAfterFirstUpdate->profile_image_path));
        $this->assertTrue($filesystem->exists($OtherUserProfileImage));

        $this->updateProfileResponse(['profile_photo' => UploadedFile::fake()->image('myPicture.jpg')->size(1000)])->assertOk();

        /** @var User */
        $userAfterSecondUpdate = User::query()->whereKey($user->id)->first();

        $this->assertFalse($filesystem->exists($userAfterFirstUpdate->profile_image_path));
        $this->assertTrue($filesystem->exists($userAfterSecondUpdate->profile_image_path));
    }
}
