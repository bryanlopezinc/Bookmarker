<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\Feature;
use App\Exceptions\FolderFeatureDisabledException;
use App\Models\Folder;
use App\Models\FolderDisabledFeature;
use App\Models\FolderFeature;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class FeatureMustBeEnabledConstraint implements Scope
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->withCasts(['updateAbleAttributesThatAreDisabled' => 'collection']);

        if ( ! $this->isUpdatingAttributeThatCanBeDisabledForUpdate()) {
            $builder->addSelect(DB::raw("(SELECT '[]') as updateAbleAttributesThatAreDisabled"));

            return;
        }

        $builder->addSelect([
            'updateAbleAttributesThatAreDisabled' => FolderFeature::query()
                ->selectRaw('JSON_ARRAYAGG(name)')
                ->whereExists(
                    FolderDisabledFeature::query()
                        ->whereColumn('folder_id', 'folders.id')
                        ->whereColumn('feature_id', 'folders_features_types.id')
                ),
        ]);
    }

    private function isUpdatingAttributeThatCanBeDisabledForUpdate(): bool
    {
        return $this->data->isUpdatingDescription ||
            $this->data->isUpdatingName           ||
            $this->data->isUpdatingIcon;
    }

    public function __invoke(Folder $folder): void
    {
        /** @var \Illuminate\Support\Collection */
        $disabledFeatures = $folder->updateAbleAttributesThatAreDisabled ?? collect();

        $folderBelongsToAuthUser = $folder->user_id === $this->data->authUser->id;

        $exception = new FolderFeatureDisabledException();

        $isDisabled = function (Feature $feature) use ($disabledFeatures): bool {
            return $disabledFeatures->contains($feature->value);
        };

        if ($folderBelongsToAuthUser || $disabledFeatures->isEmpty()) {
            return;
        }

        if ($isDisabled(Feature::UPDATE_FOLDER_NAME) && $this->data->isUpdatingName) {
            throw $exception;
        }

        if ($isDisabled(Feature::UPDATE_FOLDER_DESCRIPTION) && $this->data->isUpdatingDescription) {
            throw $exception;
        }

        if ($isDisabled(Feature::UPDATE_FOLDER_ICON) && $this->data->isUpdatingIcon) {
            throw $exception;
        }
    }
}
