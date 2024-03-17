<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Repositories\Folder\FolderSettingsSchema as Schema;

final class FolderSettingsNormalizer
{
    private readonly Schema $folderSettingsSchema;

    public function __construct(Schema $folderSettingsSchema = null)
    {
        $this->folderSettingsSchema = $folderSettingsSchema ??= new Schema();
    }

    public function fromRequest(array $folderSettings): array
    {
        $normalized = [];

        foreach ($this->dot($folderSettings) as $key) {
            $setting = $this->folderSettingsSchema->findById($key);

            $value = Arr::get($folderSettings, $setting->Id);

            Arr::set($normalized, $setting->Id, $setting->normalizer->normalize($value));
        }

        return $normalized;
    }

    private function dot(array $folderSettings): array
    {
        $dotted = [];

        foreach (array_keys(Arr::dot($folderSettings)) as $key) {
            $parts = explode('.', $key);

            if (is_numeric($last = end($parts))) {
                $key = Str::before($key, ".{$last}");
            }

            $dotted[] = $key;
        }

        return $dotted;
    }
}
