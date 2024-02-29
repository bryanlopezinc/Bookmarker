<?php

declare(strict_types=1);

namespace App\DeviceDetector;

use App\Contracts\DeviceDetectorInterface;
use App\Enums\DeviceType;
use App\ValueObjects\Device;
use Jenssegers\Agent\Agent;

final class DeviceDetector implements DeviceDetectorInterface
{
    public function fromUserAgent(string $userAgent): Device
    {
        $device = new Agent(userAgent: $userAgent);

        $deviceName = $this->getDeviceName($device);

        return match ($device->deviceType()) {
            'phone'   => new Device(DeviceType::MOBILE, $deviceName),
            'tablet'  => new Device(DeviceType::TABLET, $deviceName),
            'desktop' => new Device(DeviceType::PC, $deviceName),
            default   => new Device(DeviceType::UNKNOWN, $deviceName)
        };
    }

    private function getDeviceName(Agent $userAgent): ?string
    {
        $deviceName = $userAgent->device();

        if ($deviceName === false) {
            return null;
        }

        return (string) $deviceName;
    }
}
