<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tag as Model;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class TagFactory extends Factory
{
    protected $model = Model::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name'  => Str::random(8)
        ];
    }
}
