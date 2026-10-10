<?php

namespace App\Support;

use DeviceDetector\Cache\PSR16Bridge;
use DeviceDetector\DeviceDetector;
use Psr\SimpleCache\CacheInterface;

class ClientDetector
{
    public const BOT = 'bot';

    public const UNKNOWN = ['browser' => null, 'os' => null, 'device_type' => null];

    /**
     * The parser rules are expensive to load, so a cache keeps them across requests.
     */
    public function __construct(private readonly ?CacheInterface $cache = null) {}

    /**
     * Resolve browser, operating system and device type from a User-Agent; fields are null when unknown.
     *
     * @return array{browser: ?string, os: ?string, device_type: ?string}
     */
    public function detect(?string $userAgent): array
    {
        $unknown = self::UNKNOWN;

        if ($userAgent === null || trim($userAgent) === '') {
            return $unknown;
        }

        $detector = new DeviceDetector($userAgent);

        if ($this->cache !== null) {
            $detector->setCache(new PSR16Bridge($this->cache));
        }

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
