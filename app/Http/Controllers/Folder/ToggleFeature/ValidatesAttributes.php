<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\ToggleFeature;

use App\Enums\Feature;
use App\Exceptions\HttpException;

trait ValidatesAttributes
{
    private function validateFeature(string $feature): void
    {
        if ( ! in_array($feature, Feature::publicIdentifiers(), true)) {
            throw HttpException::notFound(['message' => 'InvalidFeatureId']);
        }
    }
}
