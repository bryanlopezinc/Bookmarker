<?php

namespace Database\Factories;

use App\ValueObjects\Username;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'username' => $this->randomUsername(),
            'firstname' => $this->faker->firstName,
            'lastname'  => $this->faker->lastName,
            'email' => $mailUsername . rand(100, 10_000) . '@' . $domain,
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
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
}
