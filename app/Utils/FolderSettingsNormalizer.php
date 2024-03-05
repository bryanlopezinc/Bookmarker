<?php

declare(strict_types=1);

namespace App\Utils;

use App\Repositories\Folder\FolderSettingsSchema as Schema;
use Illuminate\Support\Arr;

final class FolderSettingsNormalizer
{
    private readonly Schema $folderSettingsSchema;

    public function __construct(Schema $folderSettingsSchema = null)
    {
        $this->folderSettingsSchema = $folderSettingsSchema ??= new Schema();
    }

    public function fromRequest(array $folderSettings): array
    {
        foreach ($this->folderSettingsSchema->schema() as $setting) {
            if (!Arr::has($folderSettings, $setting->Id)) {
                continue;
            }

            $value = Arr::get($folderSettings, $setting->Id);

            $normalizedValue = match (true) {
                $setting->isBooleanType() => (bool) $value,
                $setting->isIntegerType() => (int) $value,
                default => $value
            };

            Arr::set($folderSettings, $setting->Id, $normalizedValue);
        }

        return $folderSettings;
    }
}
