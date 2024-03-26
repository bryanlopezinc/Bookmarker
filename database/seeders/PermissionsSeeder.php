<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FolderPermission;
use App\UAC;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class PermissionsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        UAC::all()
            ->toCollection()
            ->map(fn (string $permissionName) => ['name' => $permissionName])
            ->tap(function (Collection $values) {
                FolderPermission::query()->insertOrIgnore($values->all());
            });
    }
}
