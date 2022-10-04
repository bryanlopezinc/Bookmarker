<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SourceFactory extends Factory
{
    protected $model = Source::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $parts = explode('.', $this->faker->domainName);

        return [
            'host' => $url = Str::random(6) . '.' . $parts[1],
            'name'  => $url,
            'name_updated_at' => null
        ];
    }
}
