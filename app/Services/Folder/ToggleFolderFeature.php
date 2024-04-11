<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Enums\Feature;
use App\Exceptions\FolderNotFoundException;
use App\Http\Requests\ToggleFolderFeatureRequest as Request;
use App\Models\Folder;
use App\Models\FolderDisabledFeature as Model;
use App\Models\FolderFeature;

final class ToggleFolderFeature
{
    public function fromRequest(Request $request, int $folderId): void
    {
        $folder = Folder::query()->select(['user_id'])->whereKey($folderId)->firstOrNew();

        [$feature, $action] = $this->mapFeatures($request);

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $disabledFeature = Model::query()
            ->where($attributes = ['folder_id' => $folderId, 'feature_id' => $feature->id])
            ->firstOr(fn () => new Model($attributes));

        if ($disabledFeature->exists && $action === 'disable') {
            return;
        }

        if ($action === 'disable') {
            $disabledFeature->save();
        } else {
            $disabledFeature->delete();
        }
    }

    /**
     * @return array{0: FolderFeature, 1: string}
     */
    private function mapFeatures(Request $request): array
    {
        $feature = collect(Feature::publicIdentifiers())
            ->mapWithKeys(fn (string $value, string $key) => [$key => $request->validated($value, null)])
            ->filter();

        return [
            FolderFeature::query()->where('name', Feature::from($feature->keys()->sole())->value)->sole(),
            $feature->sole()
        ];
    }
}
