<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\FolderPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class FolderPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $seeded = FolderPermission::all(['name'])->pluck('name');

        collect(Permission::cases())
            ->map
            ->value
            ->filter(fn (string $permission) => $seeded->doesntContain($permission))
            ->map(fn (string $permission) => [
                'name' => $permission,
                'created_at' => now()
            ])
            ->whenNotEmpty(function (Collection $permissions) {
                FolderPermission::insert($permissions->all());
            });
    }
}
