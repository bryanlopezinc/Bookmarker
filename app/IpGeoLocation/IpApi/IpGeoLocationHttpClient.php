<?php

declare(strict_types=1);

namespace App\IpGeoLocation\IpApi;

use App\IpGeoLocation\IpAddress;
use App\IpGeoLocation\IpGeoLocatorInterface;
use App\IpGeoLocation\Location;
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
        $query = ['fields' => 'country,city'];

        $response = Http::retry(2, throw: false)
            ->get("http://ip-api.com/json/{$ipAddress->value}", $query)
            ->onError(function (Response $response) {
                // toException() always return an object when the onError callback is called.
                // @phpstan-ignore-next-line
                $this->logger->error($response->toException()->getMessage());
            });

        if (!$response->successful() || $response->json('status') === 'fail') {
            return Location::unknown();
        }

        return new Location($response->json('country'), $response->json('city'));
    }
}
