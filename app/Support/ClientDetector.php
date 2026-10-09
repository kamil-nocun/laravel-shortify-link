<?php

namespace App\Support;

use DeviceDetector\DeviceDetector;

class ClientDetector
{
    public const BOT = 'bot';

    /**
     * Resolve browser, operating system and device type from a User-Agent; fields are null when unknown.
     *
     * @return array{browser: ?string, os: ?string, device_type: ?string}
     */
    public function detect(?string $userAgent): array
    {
        $unknown = ['browser' => null, 'os' => null, 'device_type' => null];

        if ($userAgent === null || trim($userAgent) === '') {
            return $unknown;
        }

        $detector = new DeviceDetector($userAgent);
        $detector->parse();

        if ($detector->isBot()) {
            return [...$unknown, 'device_type' => self::BOT];
        }

        return [
            'browser' => $this->name($detector->getClient('name')),
            'os' => $this->name($detector->getOs('name')),
            'device_type' => $this->name($detector->getDeviceName()),
        ];
    }

    private function name(mixed $value): ?string
    {
        return is_string($value) && $value !== '' && $value !== DeviceDetector::UNKNOWN ? $value : null;
    }
}
