<?php

declare(strict_types=1);

namespace App\Enums;

enum BookmarkCreationSource: int
{
    case HTTP              = 1;
    case CHROME_IMPORT     = 2;
    case SAFARI_IMPORT     = 3;
    case POCKET_IMPORT     = 4;
    case INSTAPAPER_IMPORT = 5;
    case FIREFOX_IMPORT    = 6;
    case EMAIL             = 7;
}
