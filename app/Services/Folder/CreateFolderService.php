<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\Builders\FolderSettingsBuilder as Builder;
use App\Enums\FolderVisibility;
use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\Models\Folder;
use App\Models\FolderSetting;
use App\ValueObjects\UserId;
use Illuminate\Support\Collection;

final class CreateFolderService
{
    public function __invoke(Request $request): void
    {
        /** @var Folder */
        $folder = Folder::create([
            'description' => $request->validated('description'),
            'name'        => $request->validated('name'),
            'user_id'     => UserId::fromAuthUser()->value(),
            'visibility'  => (string) FolderVisibility::fromRequest($request)->value,
        ]);

        collect($this->buildSettings($request))
            ->map(fn (mixed $value, string $key) => [
                'key'       => $key,
                'value'     => $value,
                'folder_id' => $folder->id
            ])
            ->whenNotEmpty(fn (Collection $collection) => FolderSetting::insert($collection->all()));
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
