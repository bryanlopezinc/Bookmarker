<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\Feature;
use App\Http\Handlers\Constraints\FeatureMustBeEnabledConstraint as Constraint;
use App\Http\Handlers\HasHandlersInterface;

final class FeatureMustBeEnabledConstraint implements HasHandlersInterface
{
    private readonly Constraint $constraint;

    public function __construct(UpdateFolderRequestData $data)
    {
        $this->constraint = new Constraint($data->authUser, $this->getFeatures($data));
    }

    /**
     * @return array<Feature>
     */
    private function getFeatures(UpdateFolderRequestData $data): array
    {
        $features = [];

        if ($data->isUpdatingName) {
            $features[] = Feature::UPDATE_FOLDER_NAME;
        }

        if ($data->isUpdatingDescription) {
            $features[] = Feature::UPDATE_FOLDER_DESCRIPTION;
        }

        if ($data->isUpdatingIcon) {
            $features[] = Feature::UPDATE_FOLDER_ICON;
        }

        return $features;
    }

    /**
     * @inheritdoc
     */
    public function getHandlers(): array
    {
        return [$this->constraint];
    }
}
