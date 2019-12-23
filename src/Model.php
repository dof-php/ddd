<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\Util\Str;
use DOF\Util\Arr;
use DOF\Util\JSON;
use DOF\DDD\Util\TypeHint;
use DOF\DDD\Util\TypeCast;
use DOF\DDD\Entity;
use DOF\DDD\ModelManager;
use DOF\DDD\EntityManager;
use DOF\DDD\Exceptor\ModelExceptor;
use DOF\DDD\Exceptor\EntityExceptor;

/**
 * General data model
 */
abstract class Model
{
    use \DOF\Traits\Tracker;
    use \DOF\Traits\ObjectData;

    const CREATED_AT = 'createdAt';
    const REMOVED_AT = 'removedAt';
    const UPDATED_AT = 'updatedAt';
    const ON_CREATED = 'ONCREATED';
    const ON_REMOVED = 'ONREMOVED';
    const ON_UPDATED = 'ONUPDATED';
    const ON_READ = 'ONREAD';
    const ON_READ_ORIGIN = 'ONREADORIGIN';
    const ON_READ_CACHE  = 'ONREADCACHE';
    const ON_CACHE_HIT  = 'ONCACHEHIT';
    const ON_CACHE_MISS = 'ONCACHEMISS';
 
    /**
     * @Title(Meta data of model)
     * @Type(Array)
     * @Annotation(0)
     * @NoDiff(1)
     */
    private $__META__ = [
        self::ON_CREATED => true,
        self::ON_REMOVED => true,
        self::ON_UPDATED => true,
        self::ON_READ => false,
        self::ON_READ_ORIGIN => false,
        self::ON_READ_CACHE => false,
        self::ON_CACHE_HIT => true,
        self::ON_CACHE_MISS => true,
    ];

    /**
     * Compare two data model and get there differences
     *
     * @param Model $model1
     * @param Model $model2
     * @param Array|Null $nodiff: The property names of data model do not need to diff
     * @return null|array: The diff result in order [#0, #1] or null when no differences
     */
    final public static function diff(Model $model1, Model $model2, array $nodiff = null) : ?array
    {
        if (\is_null($nodiff)) {
            $nodiff = Arr::union($model1->nodiff(), $model2->nodiff());
        }

        $current = $model1->__data__();
        $compare = $__compare = $model2->__data__();

        $diff = [];
        foreach ($current as $attr => $val) {
            if (\in_array($attr, $nodiff)) {
                unset($__compare[$attr]);
                continue;
            }

            if (! \array_key_exists($attr, $compare)) {
                $diff[] = $attr;
                continue;
            }

            unset($__compare[$attr]);

            $_val = $compare[$attr] ?? null;
            if ($val !== $_val) {
                $diff[] = $attr;
            }
        }

        $diff = Arr::union($diff, \array_keys($__compare));
        if (! $diff) {
            return null;
        }

        $result = [];
        foreach ($diff as $key) {
            $result[$key] = [$current[$key] ?? null, $compare[$key] ?? null];
        }

        return $result;
    }

    /**
     * Get nodiff property names of data model/entity
     */
    final public function nodiff() : array
    {
        $attrs = static::annotation()['properties'] ?? [];
        if (! $attrs) {
            return [];
        }

        $nodiff = [];
        foreach ($attrs as $name => $attr) {
            if ($attr['NODIFF'] ?? false) {
                $nodiff[] = $name;
            }
        }

        return $nodiff;
    }

    /**
     * Compare given data model object to current model instance
     *
     * @param Model $model: The data model given to compare aginst current model instance
     * @param Array|Null $nodiff: The property names of data model do not need to diff
     * @return null|array: The diff result in order [#0-self, #1-other] or null when no differences
     */
    final public function compare(Model $model, array $nodiff = null) : ?array
    {
        return Model::diff($this, $model, $nodiff);
    }

    final public function meta(string $option = null, $value = null)
    {
        if (\is_null($option)) {
            return $this->__META__;
        }
        if (\is_null($value)) {
            return $this->__META__[\strtoupper($option)] ?? null;
        }

        $this->__META__[\strtoupper($option)] = $value;
    }

    public static function annotation()
    {
        return ModelManager::get(static::class);
    }

    final public static function init(array $data)
    {
        $class = static::class;

        if (\is_subclass_of($namespace, Entity::class) && TypeHint::uint($data['id'] ?? null)) {
            throw new EntityExceptor('ENTITY_WITHOUT_IDENTITY', \compact('data', 'class'));
        }

        $object = new $class;

        $annotation = self::annotation();

        foreach ($data as $property => $value) {
            $attr = $annotation['properties'][$property] ?? null;
            if (! $attr) {
                continue;
            }
            $type = $attr['TYPE'] ?? null;
            if (! $type) {
                throw new ModelExceptor('CLASS_PROPERTY_WITHOUT_TYPE', \compact('property', 'class'));
            }

            $object->{$property} = TypeCast::typecast($value, $type, true);
        }

        return $object;
    }

    final public function get(string $attr)
    {
        if (\property_exists($this, $attr)) {
            $val = $this->{$attr} ?? null;
            if (! \is_null($val)) {
                return $val;
            }
            $getter = 'get'.\ucfirst($attr);
            if (\method_exists($this, $getter)) {
                $params = $this->method(static::class, $getter);
                return $this->{$getter}(...$params);
            }
        }
    }

    public function set(string $attr, $val)
    {
        $annotation = $this::annotation();
        $type = $annotation['properties'][$attr]['TYPE'] ?? null;
        if ($type) {
            $val = TypeHint::convert($val, $type, true);
        }

        if (\property_exists($this, $attr)) {
            $setter = 'set'.\ucfirst($attr);
            if (\method_exists($this, $setter)) {
                $this->{$setter}($val);
            } else {
                $this->{$attr} = $val;
            }
        } else {
            $this->{$attr} = $val;
        }

        return $this;
    }

    final public function __get(string $attr)
    {
        return $this->get($attr);
    }

    final public function __set(string $attr, $val)
    {
        return $this->set($attr, $val);
    }

    final public function __call(string $method, array $params = [])
    {
        if (0 === strpos($method, 'get')) {
            if ('get' !== $method) {
                $attr = \lcfirst(\substr($method, 3));
                return $this->get($attr);
            }
        }

        if (0 === strpos($method, 'set')) {
            if ('set' !== $method) {
                $attr = \lcfirst(\substr($method, 3));

                return $this->set($attr, ($params[0] ?? null));
            }
        }

        throw new ModelExceptor('METHOD_NOT_EXISTS', [
            'class' => static::class,
            'method' => $method,
        ]);
    }
}
