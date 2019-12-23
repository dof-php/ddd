<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\ETC;
use DOF\Tracer;
use DOF\Util\IS;
use DOF\Storage\Driver;
use DOF\Storage\Storable;
use DOF\Storage\Connection;
use DOF\DDD\Storage;
use DOF\DDD\Repository;
use DOF\DDD\StorageManager;
use DOF\DDD\Exceptor\StorageAdaptorExceptor;

final class StorageAdaptor
{
    /** @var array: Storage namespace <=> Storage instance */
    private static $pool = [];

    /**
     * Initialize storage driver instance for storage class
     *
     * @param string $namespace: Namespace of storage class
     * @param \DOF\Tracer $tarcer
     * @param bool $logging
     * @return \DOF\Storage\Storable
     */
    public static function get(string $namespace, Tracer $tracer = null, bool $logging = true) : Storable
    {
        if ($instance = (self::$pool[$namespace] ?? null)) {
            return $instance;
        }

        if (! ($annotations = StorageManager::get($namespace))) {
            throw new StorageAdaptorExceptor('INVALID_STORAGE', \compact('namespace'));
        }
        if (IS::empty($driver = $annotations['meta']['DRIVER'] ?? null)) {
            throw new StorageAdaptorExceptor('STORAGE_DRIVER_MISSING', \compact('namespace'));
        }
        if (! ($storage = Driver::get($driver))) {
            throw new StorageAdaptorExceptor('UNSUPPORTED_STORAGE_DRIVER', \compact('namespace', 'driver'));
        }
        if (! ($config = ETC::final($namespace, $driver))) {
            throw new StorageAdaptorExceptor('MISSING_STORAGE_CONNNECTION_CONFIG', \compact('namespace', 'driver'));
        }

        $instance = new $storage($annotations, $logging);
        if ($tracer) {
            $tracer->trace($instance);
        }

        // Avoid an actual connection to db driver when we are using cache
        $instance->connector(function () use ($driver, $config, $annotations) {
            return Connection::get($driver, $config, $annotations);
        });

        return self::$pool[$namespace] = $instance;
    }
}
