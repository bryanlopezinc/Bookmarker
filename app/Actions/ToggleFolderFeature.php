<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Feature;
use App\Models\FolderDisabledFeature as Model;
use App\Repositories\Folder\FeaturesRepository;

final class ToggleFolderFeature
{
    private readonly FeaturesRepository $featuresRepository;

    public function __construct(FeaturesRepository $featuresRepository = null)
    {
        $this->featuresRepository = $featuresRepository ?? new FeaturesRepository();
    }

    public function enable(int $folderId, Feature $feature): void
    {
        $this->update($folderId, $feature, true);
    }

    public function disable(int $folderId, Feature $feature): void
    {
        $this->update($folderId, $feature, false);
    }

    private function update(int $folderId, Feature $feature, bool $enable): void
    {
        if ($enable) {
            Model::where('folder_id', $folderId)->delete();
        } else {
            $featureId = $this->featuresRepository->findByName($feature)->id;

            Model::query()->create(['folder_id' => $folderId, 'feature_id' => $featureId]);
        }
    }
}
