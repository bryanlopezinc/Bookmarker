<?php

namespace Database\Factories;

use App\Enums\TwoFaMode;
use App\Models\User;
use App\ValueObjects\Username;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        [$mailUsername, $domain] = explode('@', $this->faker->unique()->safeEmail());

        return [
            'username'           => $this->randomUsername(),
            'first_name'         => $this->faker->firstName,
            'last_name'          => $this->faker->lastName,
            'email'              => $mailUsername . rand(1000, 100_000) . '@' . $domain,
            'email_verified_at'  => now(),
            'password'           => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token'     => Str::random(10),
            'two_fa_mode'        => TwoFaMode::NONE,
            'profile_image_path' => null
        ];
    }

    #[\Override]
    public function configure()
    {
        return $this->afterCreating(function (User $user) {
            $user->full_name = $user->first_name . ' ' . $user->last_name;
        });
    }

    public static function randomUsername(): string
    {
        return Str::random(Username::MAX_LENGTH - 2) . '_' . rand(0, 9);
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }

    public function with2FA()
    {
        return $this->state(['two_fa_mode' => TwoFaMode::EMAIL]);
    }

    public function hasProfileImage()
    {
        return $this->state(['profile_image_path' => Str::random(40) . 'jpg']);
    }
}
