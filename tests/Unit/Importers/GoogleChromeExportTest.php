<?php

declare(strict_types=1);

namespace Tests\Unit\IpGeoLocation\IpApi;

use App\Importers\GoogleChromeExport;
use App\IpGeoLocation\IpAddress;
use App\IpGeoLocation\IpApi\IpGeoLocationHttpClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * @group 119
 */
class GoogleChromeExportTest extends TestCase
{
    public function testImport(): void
    {

        $file = new UploadedFile(base_path('tests/stubs/imports/chromeExportFile.html'), 'export_file');

        /** @var GoogleChromeExport */
        $importer = app(GoogleChromeExport::class);

        $importer->import(['export_file' => $file]);
    }
}
