<?php

declare(strict_types=1);

namespace App\Importers;

use App\Enums\ImportSource;
use App\Importers;

class Factory
{
    public function getImporter(ImportSource $source): ImporterInterface
    {
        return match ($source) {
            ImportSource::CHROME => app(Importers\Chrome\Importer::class),
            ImportSource::POCKET => app(Importers\Pocket\Importer::class),
            ImportSource::SAFARI => app(Importers\Safari\Importer::class),
            ImportSource::INSTAPAPER => app(Importers\Instapaper\Importer::class),
            ImportSource::FIREFOX => app(Importers\FireFox\Importer::class)
        };
    }
}
