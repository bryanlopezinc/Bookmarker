<?php

declare(strict_types=1);

namespace App\Importing\Enums;

use App\Enums\BookmarkCreationSource;
use App\Importing\Http\Requests\ImportBookmarkRequest;

enum ImportSource
{
    case CHROME;
    case SAFARI;
    case POCKET;
    case INSTAPAPER;
    case FIREFOX;

    public static function fromRequest(ImportBookmarkRequest $request): self
    {
        return match ($request->validated('source')) {
            $request::CHROME     => self::CHROME,
            $request::POCKET     => self::POCKET,
            $request::SAFARI     => self::SAFARI,
            $request::INSTAPAPER => self::INSTAPAPER,
            default              => self::FIREFOX
        };
    }

    public function toBookmarkCreationSource(): BookmarkCreationSource
    {
        return match ($this) {
            self::CHROME     => BookmarkCreationSource::CHROME_IMPORT,
            self::POCKET     => BookmarkCreationSource::POCKET_IMPORT,
            self::SAFARI     => BookmarkCreationSource::SAFARI_IMPORT,
            self::INSTAPAPER => BookmarkCreationSource::INSTAPAPER_IMPORT,
            self::FIREFOX    => BookmarkCreationSource::FIREFOX_IMPORT
        };
    }
}
