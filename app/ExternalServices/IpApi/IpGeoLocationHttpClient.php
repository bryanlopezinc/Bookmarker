<?php

declare(strict_types=1);

namespace App\ExternalServices\IpApi;

use App\Contracts\IpGeoLocatorInterface;
use App\ValueObjects\IpAddress;
use App\DataTransferObjects\Location;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

final class IpGeoLocationHttpClient implements IpGeoLocatorInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function getLocationFromIp(IpAddress $ipAddress): Location
    {
        $response = Http::retry(2, throw: false)
            ->get("http://ip-api.com/json/{$ipAddress->value}", ['fields' => 'country,city'])
            ->onError(function (Response $response) {
                $this->logger->error($response->toException()?->getMessage() ?? '');
            });

        if ( ! $response->successful() || $response->json('status') === 'fail') {
            return Location::unknown();
        }

        return new Location($response->json('country'), $response->json('city'));
    }
}
