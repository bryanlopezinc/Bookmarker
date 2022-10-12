<?php

namespace Database\Seeders;

use App\Models\FolderPermission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class FolderPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'viewBookmarks',
        'addBookmarks'
    ];

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $seeded = FolderPermission::all(['name'])->pluck('name');

        collect(self::PERMISSIONS)
            ->filter(fn (string $permission) => !$seeded->contains($permission))
            ->map(fn (string $permission) => [
                'name' => $permission,
                'created_at' => now()
            ])
            ->tap(function (Collection $permissions) {
                if ($permissions->isEmpty()) {
                    return;
                }

                FolderPermission::insert($permissions->all());
            });
    }
}
