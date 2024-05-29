<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\ToggleFeature;

use App\Enums\Feature;
use App\Models\User;
use App\Services\Folder\EnableFolderFeatureService;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EnableFolderFeatureController
{
    use ValidatesAttributes;

    public function __invoke(Request $request, EnableFolderFeatureService $service, string $folderId, string $feature): JsonResponse
    {
        $folderId = FolderPublicId::fromRequest($folderId);

        $this->validateFeature($feature);

        $responseCode = $service($folderId, Feature::fromPublicId($feature), User::fromRequest($request));

        return new JsonResponse(status: $responseCode);
    }
}
