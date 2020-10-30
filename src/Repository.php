<?php

declare(strict_types=1);

namespace DOF\DDD;

use Throwable;
use DOF\ENV;
use DOF\Tracer;
use DOF\Convention;
use DOF\Util\IS;
use DOF\Util\FS;
use DOF\Util\Reflect;
use DOF\DDD\Model;
use DOF\DDD\Entity;
use DOF\DDD\Storage;
use DOF\DDD\CacheAdaptor;
use DOF\DDD\RepositoryManager;
use DOF\DDD\Util\TypeHint;
use DOF\DDD\Util\TypeCast;
use DOF\DDD\Exceptor\RepositoryExceptor;

/**
 * The repository abstract persistence access, whatever storage it is. That is its purpose.
 * The fact that you're using a db or xml files or an ORM doesn't matter.
 * The Repository allows the rest of the application to ignore persistence details.
 * This way, you can easily test the app via mocking or stubbing and you can change storages if it's needed.
 *
 * Repositories deal with Domain/Business objects (from the app point of view), an ORM handles db objects.
 * A business objects IS NOT a db object, first has behaviour, the second is a glorified DTO, it only holds data.
 */
final class Repository
{
    const ORM_CACHE_DRIVER = 'ORM_CACHE_DRIVER';

    /**
     * Add ORM storage record into cache
     *
     * @param string $storage: Namespace of ORM storage class
     * @param Entity $entity: Entity object
     * @param Tracer $tracer
     */
    public static function add(string $storage, Entity $entity, Tracer $tracer = null)
    {
        self::update($storage, $entity, $tracer);
    }

    /**
     * Remove ORM storage record from cache
     *
     * @param string $storage: Namespace of ORM storage class
     * @param Entity|int $entity: Entity object
     * @param Tracer $tracer
     */
    public static function remove(string $storage, $entity, Tracer $tracer = null)
    {
        if ((! \is_int($entity)) && (! ($entity instanceof Entity))) {
            return false;
        }
        if (! self::cacheEnabled($storage)) {
            return null;
        }

        $id = \is_int($entity) ? $entity : $entity->getId();
        if ($id < 1) {
            return false;
        }

        $key = self::cacheKey($storage, $id);
        $cachable = CacheAdaptor::get($storage, $key, self::ORM_CACHE_DRIVER, $tracer);
        if (! $cachable) {
            return;
        }

        $cachable->del($key);
    }

    public static function removes(string $storage, array $ids, Tracer $tracer = null)
    {
        if (self::cacheEnabled($storage)) {
            foreach ($ids as $id) {
                if (! \is_int($id)) {
                    continue;
                }

                $key = self::cacheKey($storage, $id);
                $cachale = CacheAdaptor::get($storage, $key, self::ORM_CACHE_DRIVER, $tracer);
                if (! $cachale) {
                    continue;
                }

                $cachale->del($key);
            }
        }
    }

    /**
     * Update/Reset ORM storage record in cache
     *
     * @param string $storage: Namespace of ORM storage class
     * @param Entity $entity: Entity object
     * @param Tracer $tracer
     */
    public static function update(string $storage, Entity $entity, Tracer $tracer = null)
    {
        if (self::cacheEnabled($storage)) {
            $key = self::cacheKey($storage, $entity->getId());
            $cachable = CacheAdaptor::get($storage, $key, self::ORM_CACHE_DRIVER, $tracer);
            if ($cachable) {
                $cachable->set($key, $entity);
            }
        }
    }

    /**
     * Find ORM storage record in cache and convert it into enity object
     *
     * @param string $storage: Namespace of ORM storage class
     * @param int $id: Entity identity
     * @param Tracer $tracer
     */
    public static function find(string $storage, int $id, Tracer $tracer = null) : ?Entity
    {
        if (self::cacheEnabled($storage)) {
            $key = self::cacheKey($storage, $id);
            $cachable = CacheAdaptor::get($storage, $key, self::ORM_CACHE_DRIVER, $tracer);
            if ($cachable) {
                ($entity = $cachable->get($key)) && $tracer && $tracer->trace($entity);

                return $entity;
            }
        }

        return null;
    }

    /**
     * Mapping a storage result into an entity/model object
     *
     * @param string $storage: Namespace of storage class
     * @param array $result: An assoc array holds entity/model data
     */
    public static function map(string $storage, array $result = null, Tracer $tracer = null) : ?Model
    {
        if (! $result) {
            return null;
        }

        if (!\is_subclass_of($storage, Storage::class)) {
            throw new RepositoryExceptor('INVALID_STORAGE_CLASS', \compact('storage'));
        }

        $_storage = StorageManager::get($storage);
        if (! $_storage) {
            throw new RepositoryExceptor('INVALID_STORAGE_CLASS', \compact('storage'));
        }
        $repository = $_storage['meta']['REPOSITORY'] ?? null;
        if (! $repository) {
            throw new RepositoryExceptor('STORAGE_WITHOUT_REPOSITORY', \compact('storage'));
        }
        $_repository = RepositoryManager::get($repository);
        if (! $_repository) {
            throw new RepositoryExceptor('REPOSITORY_NOT_EXISTS', \compact('repository', 'storage'));
        }
        $implementor = $_repository['IMPLEMENTOR'] ?? null;
        if ($implementor !== $storage) {
            throw new RepositoryExceptor('INVALID_STORAGE_IMPLEMENTOR', \compact('repository', 'implementor', 'storage'));
        }
        $model = $_repository['ENTITY'] ?? ($_repository['MODEL'] ?? null);
        if (! $model) {
            throw new RepositoryExceptor('REPOSITORY_WITHOUT_DATA_MODEL', \compact('repository'));
        }

        if (\is_subclass_of($model, Entity::class)) {
            $_entity = EntityManager::get($entity = $model);
            if (! $_entity) {
                throw new RepositoryExceptor('MISSING_OR_ENTITY_NOT_EXISTS', \compact('repository', 'entity'));
            }
            $instance = new $model;
            foreach ($result as $column => $val) {
                if (! isset($_storage['columns'][$column])) {
                    continue;
                }
                $attribute = $_storage['columns'][$column];
                if (! isset($_entity['properties'][$attribute])) {
                    continue;
                }
                $property = $_entity['properties'][$attribute] ?? [];
                if (IS::confirm($property['NOMAP'] ?? false)) {
                    continue;
                }
                $type = $property['doc']['TYPE'] ?? null;
                if (IS::empty($type)) {
                    throw new RepositoryExceptor('ATTRIBUTE_WITHOUT_TYPE', \compact('type', 'attribute', 'entity'));
                }
                if (is_null($val) && \array_key_exists('DEFAULTNULL', $_storage['properties'][$attribute] ?? [])) {
                    // ignore null value if it has DEFAULTNULL option
                    continue;
                }
                if (! TypeHint::support($type)) {
                    throw new RepositoryExceptor('UNTYPEHINTABLE_TYPE', \compact('type', 'attribute', 'entity'));
                }

                try {
                    $instance->{$attribute} = TypeCast::typecast($type, $val, true);
                } catch (Throwable $th) {
                    throw new RepositoryExceptor('UNTYPECASTABLE_VALUE', \compact('attribute', 'type', 'val'), $th);
                }
            }

            if (\is_null($instance->getId())) {
                throw new RepositoryExceptor('ENTITY_WITHOUT_IDENTITY', \compact('entity', 'result'));
            }
        } elseif (\is_subclass_of($model, Model::class)) {
            $_model = ModelManager::get($model);
            if (! $_model) {
                throw new RepositoryExceptor('MISSING_OR_MODEL_NOT_EXISTS', \compact('repository', 'model'));
            }
            $instance = new $model;
            foreach ($result as $attribute => $val) {
                if (! \property_exists($instance, $attribute)) {
                    continue;
                }
                $property = $_model['properties'][$attribute] ?? [];
                if (IS::confirm($property['doc']['NOMAP'] ?? false)) {
                    continue;
                }
                $type = $property['doc']['TYPE'] ?? null;
                if ((! $type) || (! TypeHint::support($type))) {
                    throw new RepositoryExceptor('MISSING_OR_UNTYPEHINTABLE_TYPE', \compact('type', 'attribute', 'model'));
                }

                try {
                    $instance->{$attribute} = TypeCast::typecast($type, $val, true);
                } catch (Throwable $th) {
                    throw new RepositoryExceptor('UNTYPECASTABLE_VALUE', \compact('attribute', 'val', 'type'), $th);
                }
            }
        } else {
            throw new RepositoryExceptor('REPOSITORY_WITHOUT_DATA_MODEL', \compact('repository', 'model'));
        }

        if ($tracer) {
            $tracer->trace($instance);
        }
        return $instance;
    }

    public static function cacheEnabled(string $storage) : bool
    {
        $meta = StorageManager::get($storage)['meta'] ?? [];
        if (\array_key_exists('CACHE', $meta)) {
            return IS::confirm($meta['CACHE'] ?? 0);
        }

        return (bool) ENV::final($storage, 'ENABLE_ORM_CACHE', false);
    }

    /**
     * @param string $storage: Namespace of ORM storage
     * @param int $id: Entity identity
     */
    public static function cacheKey(string $storage, int $id) : string
    {
        $meta = StorageManager::get($storage)['meta'] ?? [];
        $driver = $meta['DRIVER'] ?? null;
        $dbname = $meta['DATABASE'] ?? null;
        $table  = $meta['TABLE'] ?? null;
        if (IS::empty($driver) || IS::empty($dbname) || IS::empty($table)) {
            throw new RepositoryExceptor('INVALID_ORM_STORAGE_META', \compact('storage', 'meta'));
        }

        return \strtolower(\join(':', [$driver, $dbname, $table, $id]));
    }
}
