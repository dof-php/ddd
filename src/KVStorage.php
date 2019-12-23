<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Exceptor\StorageExceptor;

class KVStorage extends Storage
{
    final public static function type() : ?string
    {
        return self::annotation()['meta']['TYPE'] ?? null;
    }

    final public static function keyraw() : ?string
    {
        return self::annotation()['meta']['KEY'] ?? null;
    }

    final public function key(...$params) : string
    {
        $key = self::keyraw();

        if (\is_null($key)) {
            throw new StorageExceptor('KVSTORAGE_KEY_MISSING', ['kv-storage' => static::class]);
        }

        return $params ? \sprintf($key, ...$params) : $key;
    }

    public function exists(...$params) : bool
    {
        if (\method_exists($this, '__exists')) {
            return (bool) $this->__exists(...$params);
        }

        $key = $this->key(...$params);

        return $this->driver()->exists($key) > 0;
    }
}
