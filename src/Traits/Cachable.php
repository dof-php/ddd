<?php

declare(strict_types=1);

namespace DOF\DDD\Traits;

use DOF\DDD\CacheAdaptor;

trait Cachable
{
    // get an cachable for a key
    final public function cache(string $key)
    {
        return new class($key, static::class, $this->tracer()) {
            private $key;
            private $cachable;
            public function __construct($key, $origin, $tracer)
            {
                $this->key = $key;
                $this->cachable = CacheAdaptor::get($origin, $key, null, $tracer);
            }
            public function __call(string $method, array $params = [])
            {
                \array_unshift($params, $this->key);
                return $this->cachable->{$method}($params);
            }
        };
    }
}
