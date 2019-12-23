<?php

declare(strict_types=1);

namespace DOF\DDD;

use Closure;
use Throwable;
use DOF\Traits\Tracker;
use DOF\Util\IS;
use DOF\Util\Format;
use DOF\Util\Paginator;
use DOF\DDD\Repository;
use DOF\DDD\StorageManager;
use DOF\DDD\StorageAdaptor;
use DOF\DDD\RepositoryManager;
use DOF\DDD\Exceptor\StorageExceptor;

/**
 * Storage is the persistence layer implementations
 */
abstract class Storage implements RepositoryInterface
{
    use Tracker;

    /**
     * @var Storage driver proxy
     * @Annotation(0)
     */
    protected $__DRIVER__;

    final public function driver()
    {
        if ($this->__DRIVER__) {
            return $this->__DRIVER__;
        }

        return $this->__DRIVER__ = StorageAdaptor::get(static::class, $this->tracer(), $this->logable('storage'));
    }

    final public function storage(Closure $callback)
    {
        return $callback($this->driver());
    }

    final public static function annotation()
    {
        return StorageManager::get(static::class);
    }

    /**
     * Convert an array result data into entity/model object
     */
    final public function convert(array $result = null) : ?Model
    {
        if (! $result) {
            return null;
        }

        $storage = static::class;
        if (! IS::array($result, 'assoc')) {
            throw new StorageExceptor('UNCONVERTABLE_NONASSOC_ARRAY_RESULT', \compact('result', 'storage'));
        }

        return Repository::map($storage, $result, $this->tracer());
    }

    /**
     * Convert a list of results or a paginator instance into entity/model object list
     */
    final public function converts($result = null)
    {
        if (! $result) {
            return [];
        }

        $tracer = $this->tracer();

        $storage = static::class;
        if ($result instanceof Paginator) {
            $list = $result->getList();
            foreach ($list as &$item) {
                $item = Repository::map($storage, $item, $tracer);
            }
            $result->setList($list);

            return $result;
        }

        if (! IS::array($result, 'index')) {
            throw new StorageExceptor('UNCONVERTABLE_NONINDEX_ARRAY_RESULT', \compact('result', 'storage'));
        }

        foreach ($result as &$item) {
            $item = Repository::map($storage, $item, $tracer);
        }

        return $result;
    }
}
