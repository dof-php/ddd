<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DMN;
use DOF\Convention;
use DOF\Traits\Manager;
use DOF\Util\FS;
use DOF\Util\Str;
use DOF\Util\Reflect;
use DOF\Storage\Driver;
use DOF\DDD\RepositoryInterface;
use DOF\DDD\Storage;
use DOF\DDD\Exceptor\StorageManagerExceptor;

final class StorageManager
{
    use Manager;

    public static function init()
    {
        foreach (DMN::list() as $domain => $dir) {
            if (\is_dir($path = FS::path($dir, Convention::DIR_STORAGE))) {
                StorageManager::addDomain($domain, $path);
            }
        }
    }

    /**
     * Assemble Repository From Annotations
     */
    public static function assemble(array $ofClass, array $ofProperties, array $ofMethods, string $type)
    {
        $namespace = $ofClass['namespace'] ?? null;
        if (! $namespace) {
            return;
        }
        if ($exists = (self::$storages[$namespace] ?? false)) {
            throw new StorageManagerExceptor('DUPLICATE_STORAGE_CLASS', \compact('exists'));
        }
        if (! \is_subclass_of($namespace, Storage::class)) {
            throw new StorageManagerExceptor('INVALID_STORAGE_CLASS', \compact('namespace'));
        }
        $driver = $ofClass['doc']['DRIVER'] ?? null;
        if (! $driver) {
            throw new StorageManagerExceptor('STORAGE_DRIVER_MISSING', \compact('namespace'));
        }
        if (! Driver::support($driver)) {
            throw new StorageManagerExceptor('STORAGE_DRIVER_NOT_SUPPORT_YET', \compact('driver'));
        }
        // Require database annotation here coz DOF\Storage\Connection will keep the first db name
        // When first connect to driver server and if other storages have different db name
        // Then it will coz table not found kind of errors
        if (Driver::database($driver) && (! ($ofClass['doc']['DATABASE'] ?? null))) {
            throw new StorageManagerExceptor('STORAGE_DATABASE_NAME_MISSING', \compact('namespace', 'driver'));
        }
        if (Driver::table($driver) && (! ($ofClass['doc']['TABLE'] ?? null))) {
            throw new StorageManagerExceptor('STORAGE_TABLE_NAME_MISSING', \compact('namespace', 'driver'));
        }

        self::$data[$namespace]['meta'] = $ofClass['doc'] ?? [];
        foreach ($ofProperties as $property => $attr) {
            $_column = $attr['doc'] ?? [];
            $column  = $_column['COLUMN'] ?? false;
            if ($column) {
                self::$data[$namespace]['columns'][$column] = $property;
                self::$data[$namespace]['properties'][$property] = $_column;
            }
        }

        switch ($type) {
            case Convention::SRC_DOMAIN:
                self::${$type}[$namespace] = DMN::name($namespace);
                break;
            case Convention::SRC_VENDOR:
                break;
            case Convention::SRC_SYSTEM:
            default:
                break;
        }
    }

    public static function __annotationValueMetaUNIQUE(string $index, string $storage, &$multiple, &$strict, array $ext)
    {
        return self::__annotationValueMetaINDEX($index, $storage, $multiple, $strict, $ext);
    }

    public static function __annotationValueMetaINDEX(string $index, string $storage, &$multiple, &$strict, array $ext)
    {
        $multiple = 'isolate';

        $fields = Str::arr($ext['OF'] ?? '', ',');
        if (\count($fields) < 1) {
            throw new StorageManagerExceptor('MISSING_STORAGE_INDEX_FIELDS', \compact('index', 'storage'));
        }

        return [$index => $fields];
    }

    public static function __annotationValueMetaREPOSITORY(string $repository, string $storage)
    {
        $_repository = Reflect::getAnnotationNamespace($repository, $storage);
        if ((! $_repository) || (! interface_exists($_repository))) {
            throw new StorageManagerExceptor('MISSING_OR_REPOSITORY_NOT_EXISTS', \compact('repository', 'storage'));
        }

        if (!\is_subclass_of($_repository, RepositoryInterface::class)) {
            throw new StorageManagerExceptor('INVALID_REPOSITORY_INTERFACE', \compact('repository', 'storage'));
        }

        return $_repository;
    }
}
