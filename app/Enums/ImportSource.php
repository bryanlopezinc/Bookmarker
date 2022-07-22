<?php

declare(strict_types=1);

namespace App\Enums;

use App\Http\Requests\ImportBookmarkRequest;

enum ImportSource
{
    case CHROME_FILE;
    case SAFARI_FILE;
    case POCKET_EXPORT_FILE;

    public static function fromRequest(ImportBookmarkRequest $request): self
    {
        return match ($request->validated('source')) {
            $request::CHROME => self::CHROME_FILE,
            $request::POCKET => self::POCKET_EXPORT_FILE,
            $request::SAFARI => self::SAFARI_FILE
        };
    }

    public function isFromSafari(): bool
    {
        return $this === self::SAFARI_FILE;
    }

    public function isFromChrome(): bool
    {
        return $this === self::CHROME_FILE;
    }

    public function isFromPocketExportFile(): bool
    {
        return $this === self::POCKET_EXPORT_FILE;
    }
}
