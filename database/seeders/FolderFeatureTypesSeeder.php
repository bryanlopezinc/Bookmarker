<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Feature;
use App\Models\FolderFeature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class FolderFeatureTypesSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        collect(Feature::cases())
            ->pluck('value')
            ->map(fn (string $name) => ['name' => $name])
            ->tap(function (Collection $values) {
                FolderFeature::query()->insertOrIgnore($values->all());
            });
    }
}
