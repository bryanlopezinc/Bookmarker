<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\Builders\FolderSettingsBuilder as Builder;
use App\ValueObjects\FolderSettings;
use App\Enums\FolderVisibility;
use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\Models\Folder;

final class CreateFolderService
{
    public function __invoke(Request $request): void
    {
        Folder::create([
            'description' => $request->validated('description'),
            'name'        => $request->validated('name'),
            'user_id'     => auth()->id(),
            'visibility'  => FolderVisibility::fromRequest($request),
            'settings'    => new FolderSettings($this->buildSettings($request))
        ]);
    }

    private function buildSettings(Request $request): array
    {
        if ($request->missing('settings')) {
            return [];
        }

        return Builder::fromRequest(json_decode($request->validated('settings'), true))
            ->build()
            ->toArray();
    }
}
