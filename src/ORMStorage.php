<?php

declare(strict_types=1);

namespace DOF\DDD;

use Closure;
use Throwable;
use DOF\Util\Collection;
use DOF\DDD\Repository;
use DOF\DDD\StorageManager;
use DOF\DDD\Util\TypeHint;
use DOF\DDD\Util\TypeCast;
use DOF\DDD\Exceptor\StorageExceptor;
use DOF\DDD\Event\EntityCreated;
use DOF\DDD\Event\EntityRemoved;
use DOF\DDD\Event\EntityUpdated;

/**
 * In DOF, ORMStorage also the configuration of ORM
 */
class ORMStorage extends Storage
{
    /**
     * @Column(id)
     * @Type(int)
     * @Length(10)
     * @Unsigned(1)
     * @AutoInc(1)
     * @Notnull(1)
     */
    protected $id;

    final public function builder()
    {
        return $this->driver()->builder();
    }

    final public function count() : int
    {
        return $this->builder()->count();
    }

    final public function paginate(int $page, int $size)
    {
        return $this->converts($this->builder()->paginate($page, $size));
    }

    final public static function table(bool $database = false, bool $prefix = true) : string
    {
        $meta = self::annotation()['meta'] ?? [];
        $table = $meta['TABLE'] ?? '';
        if ($prefix) {
            $prefix = $meta['PREFIX'] ?? '';
        }
        if ($database && ($database = $meta['DATABASE'] ?? '')) {
            $database = "`{$database}`.";
        }

        return "{$database}`{$prefix}{$table}`";
    }

    final public function add(Entity &$entity) : Entity
    {
        $storage = static::class;
        $annotation = StorageManager::get($storage);
        $columns = $annotation['columns'] ?? [];
        if (! $columns) {
            throw new StorageExceptor('NO_COLUMNS_ON_STORAGE_TO_ADD', \compact('storage'));
        }

        if (\property_exists($entity, Model::CREATED_AT) && \is_null($entity->{Model::CREATED_AT})) {
            $entity->{Model::CREATED_AT} = \time();
        }
        if (\property_exists($entity, Model::UPDATED_AT)) {
            $entity->{Model::UPDATED_AT} = 0;
        }

        unset($columns['id']);

        $data = [];
        foreach ($columns as $column => $property) {
            $attribute = $annotation['properties'][$property] ?? [];
            // Ignore insert with column if property not exists in entity
            if (! \property_exists($entity, $property)) {
                continue;
            }
            $val = $entity->{$property} ?? null;
            // Null value check and set default if necessary
            if (\is_null($val)) {
                $val = $attribute['DEFAULT'] ?? null;
            }
            // Skip typecast if value is null and default value is null
            if (\is_null($val) && \array_key_exists('DEFAULTNULL', $attribute)) {
                continue;
            }

            $type = $attribute['TYPE'] ?? null;
            if (! $type) {
                $entity = \get_class($entity);
                throw new StorageExceptor('MISSING_ENTITY_TYPE', \compact('type', 'attribute', 'storage', 'entity'));
            }
            if (! TypeHint::support($type)) {
                $entity = \get_class($entity);
                throw new StorageExceptor('UNSUPPORTED_ENTITY_TYPE', \compact('type', 'attribute', 'storage', 'entity'));
            }

            $data[$column] = TypeCast::typecast($type, $val, true);
        }

        if (! $data) {
            throw new StorageExceptor('NO_DATA_FOR_STORAGE_TO_ADD', [
                'storage' => static::class,
                'entity'  => \get_class($entity),
            ]);
        }

        $entity->setId($this->driver()->add($data));

        // Add entity into repository cache
        Repository::add($storage, $entity, $this->tracer());

        if ($entity->meta(Model::ON_CREATED) && ($listeners = ($entity::annotation()['meta']['doc'][Model::ON_CREATED] ?? []))) {
            $this->new(EntityCreated::class)->listen(...$listeners)->setEntity($entity)->publish();
        }

        return $entity;
    }

    final public function removes(array $ids)
    {
        foreach (\array_unique($ids) as $id) {
            if (TypeHint::pint($id)) {
                $this->remove(\intval($id));
            }
        }
    }

    final public function remove($entity) : ?int
    {
        if ((! \is_int($entity)) && (! ($entity instanceof Entity))) {
            return 0;
        }
        if (\is_int($entity)) {
            if ($entity < 1) {
                return 0;
            }

            $entity = $this->find($entity);
            if (! $entity) {
                return 0;
            }
        }

        $id = $entity->getId();
        $storage = static::class;

        try {
            // Ignore when entity not exists in repository
            $result = $this->driver()->delete($id);
            // Remove entity from repository cache
            Repository::remove($storage, $entity, $this->tracer());
            if (\property_exists($entity, Model::REMOVED_AT)) {
                $entity->{Model::REMOVED_AT} = \time();
            }

            if ($result > 0) {
                if ($entity->meta(Model::ON_REMOVED) && ($listeners = ($entity::annotation()['meta']['doc'][Model::ON_REMOVED] ?? []))) {
                    $this->new(EntityRemoved::class)->listen(...$listeners)->setEntity($entity)->publish();
                }
            }

            return $result;
        } catch (Throwable $th) {
            $entity = \get_class($entity);
            throw new StorageExceptor('REMOVE_ENTITY_FAILED', \compact('entity', 'id', 'storage'), $th);
        }
    }

    final public function save(Entity &$entity, &$updated = false) : ?Entity
    {
        $_id = $entity->getId();
        if ((! \is_int($_id)) || ($_id < 1)) {
            return null;
        }
        $_entity = $this->find($_id);
        if (! $_entity) {
            return null;
        }

        $diff = $entity->compare($_entity);
        if (! $diff) {
            return $entity;
        }

        $storage = static::class;
        $annotation = StorageManager::get($storage);
        $columns = $annotation['columns'] ?? [];

        if (! $columns) {
            throw new StorageExceptor('NO_COLUMNS_ON_STORAGE_TO_UPDATE', \compact('storage'));
        }

        if (\property_exists($entity, Model::UPDATED_AT)) {
            $before = $entity->{Model::UPDATED_AT};
            $entity->{Model::UPDATED_AT} = $latest = \time();
            $diff[Model::UPDATED_AT] = [$before, $latest];
        }

        // Primary key is not allowed to update
        unset($columns['id']);

        $data = [];
        foreach ($columns as $column => $property) {
            if (! \is_array($diff[$property] ?? null)) {
                continue;
            }
            if (\property_exists($entity, Model::CREATED_AT) && ($property === Model::CREATED_AT)) {
                continue;
            }

            $attribute = $annotation['properties'][$property] ?? [];
            $val = $entity->{$property} ?? null;
            // Null value check and set default if specific
            if (\is_null($val)) {
                $val = $attribute['DEFAULT'] ?? null;
            }

            $type = $attribute['TYPE'] ?? null;
            if (! $type) {
                $entity = \get_class($entity);
                throw new StorageExceptor('MISSING_ENTITY_TYPE', \compact('type', 'attribute', 'storage', 'entity'));
            }
            if (! TypeHint::support($type)) {
                $entity = \get_class($entity);
                throw new StorageExceptor('UNSUPPORTED_ENTITY_TYPE', \compact('type', 'attribute', 'storage', 'entity'));
            }

            $data[$column] = TypeCast::{$type}($val, true);
        }

        if (! $data) {
            throw new StorageExceptor('NO_DATA_FOR_STORAGE_TO_UPDATE', [
                'storage' => static::class,
                'entity'  => \get_class($entity),
            ]);
        }

        $this->driver()->update($entity->getId(), $data);

        // Update/Reset repository cache
        Repository::update($storage, $entity, $this->tracer());

        if ($entity->meta(Model::ON_UPDATED) && ($listeners = ($entity::annotation()['meta']['doc'][Model::ON_UPDATED] ?? []))) {
            $this->new(EntityUpdated::class)
                ->listen(...$listeners)
                ->setEntity($entity)
                ->setDiff($diff)
                ->publish();
        }

        $updated = true;

        return $entity;
    }

    final public function finds(array $ids)
    {
        $list = [];

        $ids = \array_unique($ids);
        foreach ($ids as $id) {
            if (! TypeHint::pint($id)) {
                continue;
            }
            $id = TypeCast::typecast($id);
            if ($entity = $this->find($id)) {
                $list[] = $entity;
            }
        }

        return $list;
    }

    final public function find(int $id) : ?Entity
    {
        $class = static::class;
        $tracer = $this->tracer();
        // Find in repository cache first
        if ($entity = Repository::find($class, $id, $tracer)) {
            return $entity;
        }

        $result = $this->driver()->find($id);
        if (! $result) {
            return null;
        }

        $entity = Repository::map($class, $result, $tracer);
        Repository::add(static::class, $entity, $tracer);

        return $entity;
    }

    final public function filter(
        Closure $filter,
        int $page,
        int $size,
        string $sortField = null,
        string $sortOrder = null
    ) {
        $builder = $this->sorter($sortField, $sortOrder);

        $result = $filter($builder);
        if (\is_null($result)) {
            return $this->converts($builder->paginate($page, $size));
        }

        return $result;
    }

    final public function sorter(string $sortField = null, string $sortOrder = null)
    {
        $builder = $this->builder();

        if ($sortField && ($column = $this->column($sortField))) {
            $builder->order($column, $sortOrder ?: 'desc');
        } else {
            $builder->order('id', 'desc');
        }

        return $builder;
    }

    public function list(
        int $page,
        int $size,
        Collection $filter = null,
        string $sortField = null,
        string $sortOrder = null
    ) {
        // TO-BE-OVERWRITE
        return $this->converts($this->sorter($sortField, $sortOrder)->paginate($page, $size));
    }

    final public function column(string $attr) : ?string
    {
        return $this->annotation()['properties'][$attr]['COLUMN'] ?? null;
    }

    /**
     * Set a value for a entity attr without guaranteeing record existence
     */
    final public function set(int $id, string $attr, $value)
    {
        $column = $this->column($attr);
        if (! $column) {
            throw new StorageExceptor('MISSING_COLUMN_OF_ATTRIBUTE_TO_SET', \compact('attr'));
        }

        $result = $this->builder()->where('id', $id)->set($column, $value);

        if ($result > 0) {
            Repository::remove(static::class, $id, $this->tracer());
        }

        return $this;
    }

    /**
     * Set a batch value for entity attrs without guaranteeing record existence
     */
    final public function update(int $id, array $data)
    {
        unset($data['id']);

        $_data = [];

        foreach ($data as $attr => $value) {
            $column = $this->column($attr);
            if (! $column) {
                throw new StorageExceptor('MISSING_COLUMN_OF_ATTRIBUTE_TO_SET', \compact('attr'));
            }
            $_data[$column] = $value;
        }

        if (! $_data) {
            return $this;
        }

        $result = $this->builder()->where('id', $id)->update($_data);

        if ($result > 0) {
            Repository::remove(static::class, $id, $this->tracer());
        }

        return $this;
    }

    /**
     * Flush entity cache only - single
     *
     * @param int $id
     */
    final public function flush(int $id)
    {
        Repository::remove(static::class, $id, $this->tracer());
    }

    /**
     * Flush entity cache only - multiples
     *
     * @param array $ids
     */
    final public function flushs(array $ids)
    {
        Repository::removes(static::class, $ids, $this->tracer());
    }
}
