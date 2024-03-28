<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\Contracts\FolderRequestHandlerInterface;
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

final class FeatureMustBeEnabledConstraint implements Scope, FolderRequestHandlerInterface
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $isUpdatingAttributeThatCanBeDisabledForUpdate = $this->data->hasDescription || $this->data->name !== null;

        $builder->withCasts(['updateAbleAttributesThatAreDisabled' => 'collection']);

        if ( ! $isUpdatingAttributeThatCanBeDisabledForUpdate) {
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

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        /** @var \Illuminate\Support\Collection */
        $disabledFeatures = $folder->updateAbleAttributesThatAreDisabled ?? collect();

        $folderBelongsToAuthUser = $folder->user_id === $this->data->authUser->id;

        $exception = new FolderFeatureDisabledException();

        if ($folderBelongsToAuthUser || $disabledFeatures->isEmpty()) {
            return;
        }

        if ($disabledFeatures->contains(Feature::UPDATE_FOLDER->value)) {
            throw $exception;
        }

        if ($disabledFeatures->contains(Feature::UPDATE_FOLDER_NAME->value) && $this->data->name !== null) {
            throw $exception;
        }

        if ($disabledFeatures->contains(Feature::UPDATE_FOLDER_DESCRIPTION->value) && $this->data->hasDescription) {
            throw $exception;
        }
    }
}
