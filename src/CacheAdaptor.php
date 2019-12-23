<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DOF;
use DOF\ETC;
use DOF\ENV;
use DOF\Tracer;
use DOF\Convention;
use DOF\Util\Str;
use DOF\Storage\Connection;
use DOF\Cache\Driver;
use DOF\Cache\Cachable;
use DOF\DDD\Exceptor\CacheAdaptorExceptor;

final class CacheAdaptor
{
    const CACHE_DRIVER = 'CACHE_DRIVER';
    const CACHE_FILE = 'file';
    
    /** @var array: Cache origin <=> Cachable instance */
    private static $pool = [];

    /**
     * Get a cachable instance for a domain origin
     *
     * @param string $origin: One type of domain origin
     * @param string $key: The key of cache data
     * @param string $cfg: Customized cache driver when necessary
     * @param \DOF\Tracer $tracer
     * @param bool $logging: Logging or not where query the driver
     * @return \DOF\Cache\Cachable|null
     */
    public static function get(string $origin, string $key, string $cfg = null, Tracer $tracer = null, bool $logging = true) : ?Cachable
    {
        if ($instance = (self::$pool[$origin] ?? null)) {
            return $instance;
        }
        if (! ($driver = ENV::finalMatch($origin, (\is_null($cfg) ? [self::CACHE_DRIVER] : [$cfg, self::CACHE_DRIVER]), self::CACHE_FILE))) {
            return null;
        }
        if (! ($cachable = Driver::get($driver))) {
            throw new CacheAdaptorExceptor('UNSUPPORTED_CACHE_DRIVER', \compact('driver'));
        }
        $config = $option = [];
        if ($file = Str::eq($driver, self::CACHE_FILE)) {
            $option = ['path' => DOF::path(Convention::DIR_RUNTIME, Convention::DIR_CACHE)];
        } else {
            if (! ($config = ETC::final($origin, $driver))) {
                throw new CacheAdaptorExceptor('MISSING_OR_EMPTY_CACHE_DRIVER_CONFIG', \compact('driver'));
            }
            $option = Driver::option($driver, $key, $config);
        }

        $instance = new $cachable($option, $logging);
        if ($tracer) {
            $tracer->trace($instance);
        }

        if ($file) {
            $instance->connected();
        } else {
            $instance->connector(function () use ($driver, $config, $option) {
                return Connection::get($driver, $config, $option);
            });
        }

        return self::$pool[$origin] = $instance;
    }
}
