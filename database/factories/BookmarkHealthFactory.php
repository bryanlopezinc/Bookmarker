<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BookmarkHealth;
use Illuminate\Database\Eloquent\Factories\Factory;

final class BookmarkHealthFactory extends Factory
{
    protected $model = BookmarkHealth::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'is_healthy' => true,
            'last_checked' => today()
        ];
    }

    public function unHealthy(): self
    {
        return $this->state([
            'is_healthy' => false,
        ]);
    }

    public function checkedDaysAgo(int $days): self
    {
        return $this->state([
            'last_checked' => today()->subDays($days)
        ]);
    }
}
