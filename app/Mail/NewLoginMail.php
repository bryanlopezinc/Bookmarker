<?php

declare(strict_types=1);

namespace App\Mail;

use App\LoginInformation;
use Illuminate\Mail\Mailable;

final class NewLoginMail extends Mailable
{
    public function __construct(public readonly LoginInformation $loginInfo)
    {
    }

    public function build(): self
    {
        return $this->view('emails.newlogin', [
            'deviceType'  => $this->getDeviceType(),
            'deviceName' => $this->loginInfo->device->name,
            'deviceNameIsKnown' => $this->loginInfo->device->nameIsKnown(),
            'locationIsUnKnown' => !$this->loginInfo->location->isUnknown(),
            'country' => $this->loginInfo->location->country,
            'city' => $this->loginInfo->location->city,
            'countryisKnown' => $this->loginInfo->location->countryIsKnown(),
            'cityisKnown' => $this->loginInfo->location->cityIsKnown()
        ]);
    }

    private function getDeviceType(): string
    {
        $device = $this->loginInfo->device->type;

        return match ($device) {
            $device::MOBILE => 'Mobile',
            $device::TABLET => 'Tablet',
            $device::PC  => 'Desktop',
            $device::UNKNOWN => 'Unknown',
        };
    }
}
