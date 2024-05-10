<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Feature;
use App\Models\FolderDisabledFeature as Model;
use App\Models\FolderFeature;
use Illuminate\Database\Query\Expression;

final class ToggleFolderFeature
{
    public function disable(int $folderId, Feature $feature): void
    {
        $this->update($folderId, $feature, false);
    }

    public function enable(int $folderId, Feature $feature): void
    {
        $this->update($folderId, $feature, true);
    }

    private function update(int $folderId, Feature $feature, bool $enable): void
    {
        if ($enable) {
            Model::query()
                ->where('folder_id', $folderId)
                ->where('feature_id', FolderFeature::select('id')->where('name', $feature->name))
                ->delete();

            return;
        }

        /** @var \Illuminate\Database\Eloquent\Builder */
        $query = FolderFeature::query()
            ->select(new Expression($folderId), 'id')
            ->where('name', $feature->value);

        Model::query()->insertUsing(['folder_id', 'feature_id'], $query);
    }
}
