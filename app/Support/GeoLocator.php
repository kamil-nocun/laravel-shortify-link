<?php

namespace App\Support;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use InvalidArgumentException;
use MaxMind\Db\Reader\InvalidDatabaseException;

class GeoLocator
{
    private ?Reader $reader = null;

    /**
     * Resolve the country and city of an IP; every field is null when the address cannot be located.
     *
     * @return array{country_code: ?string, country: ?string, city: ?string}
     */
    public function locate(string $ip): array
    {
        $unknown = ['country_code' => null, 'country' => null, 'city' => null];

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return $unknown;
        }

        try {
            $record = $this->reader()?->city($ip);
        } catch (AddressNotFoundException|InvalidArgumentException|InvalidDatabaseException) {
            return $unknown;
        }

        if ($record === null) {
            return $unknown;
        }

        return [
            'country_code' => $record->country->isoCode,
            'country' => $record->country->name,
            'city' => $record->city->name,
        ];
    }

    private function reader(): ?Reader
    {
        $path = config('services.geoip.database');

        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        return $this->reader ??= new Reader($path);
    }
}
