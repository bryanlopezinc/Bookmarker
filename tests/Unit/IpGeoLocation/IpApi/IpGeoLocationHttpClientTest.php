<?php

declare(strict_types=1);

namespace Tests\Unit\IpGeoLocation\IpApi;

use App\ExternalServices\IpApi\IpGeoLocationHttpClient;
use App\ValueObjects\IpAddress;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class IpGeoLocationHttpClientTest extends TestCase
{
    public function testWillLogErrorResponse(): void
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger->expects($this->once())->method('error');

        Http::fake(fn () => Http::response(status: 403));

        $client = new IpGeoLocationHttpClient($logger);

        $this->assertTrue($client->getLocationFromIp(new IpAddress('127.0.0.1'))->isUnknown());
    }

    public function testSuccessResponse(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response('{
                    "country": "Canada",
                    "city": "Montreal"
            }'),
        ]);

        $location = (new IpGeoLocationHttpClient(app('log')))->getLocationFromIp(new IpAddress('127.0.0.1'));

        $this->assertEquals('Canada', $location->country);
        $this->assertEquals('Montreal', $location->city);
    }
}
