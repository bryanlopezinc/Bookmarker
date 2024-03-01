<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Enums\Feature;
use App\Models\FolderFeature;
use Illuminate\Support\Collection;

final class FeaturesRepository
{
    /**
     * @var array<array{
     *   id: int,
     *   name: string,
     *   created_at: string
     *  }>
     */
    private const ROWS = [
        ['id' => 1, 'name' => 'ADD_BOOKMARKS',    'created_at' => '2023-12-12 15:48:29'],
        ['id' => 2, 'name' => 'DELETE_BOOKMARKS', 'created_at' => '2023-12-12 15:48:29'],
        ['id' => 3, 'name' => 'SEND_INVITES',     'created_at' => '2023-12-12 15:48:29'],
        ['id' => 4, 'name' => 'UPDATE_FOLDER',    'created_at' => '2023-12-12 15:48:29'],
        ['id' => 5, 'name' => 'JOIN_FOLDER',      'created_at' => '2023-12-12 15:48:29'],
    ];

    public function findByName(Feature $feature): FolderFeature
    {
        return $this->findManyByName([$feature])->sole();
    }

    /**
     * @param iterable<Feature> $features
     *
     * @return Collection<FolderFeature>
     */
    public function findManyByName(iterable $features): Collection
    {
        $features = collect($features)->map(fn (Feature $feature) => $feature->value)->all();

        return collect(self::ROWS)->whereIn('name', $features)->mapInto(FolderFeature::class);
    }

    /**
     * @param iterable<int> $featuresIds
     *
     * @return Collection<FolderFeature>
     */
    public function findManyById(iterable $featuresIds): Collection
    {
        $featuresIds = collect($featuresIds)->map(fn (int $id) => $id)->all();

        return collect(self::ROWS)->whereIn('id', collect($featuresIds)->all())->mapInto(FolderFeature::class);
    }
}
