<?php

declare(strict_types=1);

namespace App\Enums;

use App\Http\Requests\ImportBookmarkRequest;

enum ImportSource
{
    case CHROME;
    case SAFARI;
    case POCKET;
    case INSTAPAPER;

    public static function fromRequest(ImportBookmarkRequest $request): self
    {
        return match ($request->validated('source')) {
            $request::CHROME => self::CHROME,
            $request::POCKET => self::POCKET,
            $request::SAFARI => self::SAFARI,
            $request::INSTAPAPER=> self::INSTAPAPER
        };
    }
}
