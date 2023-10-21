<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SecondaryEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

final class EmailFactory extends Factory
{
    protected $model = SecondaryEmail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'email'       => $this->faker->unique()->email,
            'verified_at' => now(),
            'user_id'     => UserFactory::new(),
        ];
    }
}
