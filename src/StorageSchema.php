<?php

declare(strict_types=1);

namespace DOF\DDD;

use Throwable;
use DOF\Util\IS;
use DOF\Util\Singleton;
use DOF\DDD\StorageManager;
use DOF\DDD\StorageAdaptor;
use DOF\DDD\Exceptor\StorageSchemaExceptor;
use DOF\Storage\Schema;

final class StorageSchema
{
    public static function prepare(string $storage)
    {
        if (! \class_exists($storage)) {
            throw new StorageSchemaExceptor('STORAGE_CLASS_NOT_EXISTS', \compact('storage'));
        }

        $annotations = StorageManager::get($storage);

        if (! ($driver = $annotations['meta']['DRIVER'] ?? null)) {
            throw new StorageSchemaExceptor('STORAGE_DRIVER_NOT_SET', \compact('storage'));
        }

        if (! ($schema = Schema::get($driver))) {
            throw new StorageSchemaExceptor('UNSUPPOERTED_STORAGE_DRIVER', \compact('storage', 'driver'));
        }

        return [$schema, $annotations];
    }

    /**
     * Sync a storage orm schema to their driver from their annotations
     *
     * @param string $storage: Namespace of storage orm class
     */
    public static function sync(
        string $storage,
        bool $force = false,
        bool $dump = false,
        bool $logging = false
    ) {
        list($driver, $annotations) = self::prepare($storage);

        try {
            return Singleton::get($driver)->reset()
                ->setStorage($storage)
                ->setAnnotations($annotations)
                ->setDriver(StorageAdaptor::get($storage, null, $logging))
                ->setForce($force)
                ->setDump($dump)
                ->sync();
        } catch (Throwable $th) {
            throw new StorageSchemaExceptor('SYNC_ORM_STORAGE_EXCEPTION', \compact('storage', 'force', 'dump'), $th);
        }
    }

    public static function init(
        string $storage,
        bool $force = false,
        bool $dump = false,
        bool $logging = false
    ) {
        list($driver, $annotations) = self::prepare($storage);

        try {
            return Singleton::get($driver)->reset()
                ->setStorage($storage)
                ->setAnnotations($annotations)
                ->setDriver(StorageAdaptor::get($storage, null, $logging))
                ->setForce($force)
                ->setDump($dump)
                ->init();
        } catch (Throwable $th) {
            throw new StorageSchemaExceptor('INIT_ORM_STORAGE_EXCEPTION', \compact('storage', 'force', 'dump'), $th);
        }
    }
}
