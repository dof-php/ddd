<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DMN;
use DOF\Convention;
use DOF\Traits\Manager;
use DOF\Util\FS;
use DOF\Util\Reflect;
use DOF\DDD\Event;
use DOF\DDD\Listener;
use DOF\DDD\Exceptor\EventManagerExceptor;
use DOF\DDD\Event\EntityCreated;
use DOF\DDD\Event\EntityRemoved;
use DOF\DDD\Event\EntityUpdated;
use DOF\DDD\Event\ModelCreated;
use DOF\DDD\Event\ModelRemoved;
use DOF\DDD\Event\ModelUpdated;

final class EventManager
{
    use Manager;

    public static function init()
    {
        EventManager::addSystem([
            EntityCreated::class,
            EntityRemoved::class,
            EntityUpdated::class,
            ModelCreated::class,
            ModelRemoved::class,
            ModelUpdated::class,
        ]);

        foreach (DMN::list() as $domain => $dir) {
            if (\is_dir($path = FS::path($dir, Convention::DIR_EVENT))) {
                EventManager::addDomain($domain, $path);
            }
        }
    }

    public static function assemble(array $ofClass, array $ofProperties, array $ofMethods, string $type)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if (!\is_subclass_of($namespace, Event::class)) {
            throw new EventManagerExceptor('INVALID_EVENT_CLASS', \compact('namespace'));
        }
        if ($exists = (self::$data[$namespace] ?? false)) {
            throw new EventManagerExceptor('DUPLICATE_EVENT', \compact('namespace'));
        }
        // if (! ($ofClass['doc']['TITLE'] ?? false)) {
        // throw new EventManagerExceptor('EVENT_WITHOUT_TITLE', \compact('namespace'));
        // }

        self::$data[$namespace]['meta'] = $ofClass;
        self::$data[$namespace]['properties'] = $ofProperties;
    }

    public static function __annotationValueMetaLISTENER(string $listener, string $event, &$multiple, &$strict)
    {
        $multiple = 'unique';

        $_listener = Reflect::getAnnotationNamespace($listener, $event);
        if (! $_listener) {
            throw new EventManagerExceptor('EVENT_LISTENER_NOT_EXISTS', \compact('listener', 'event'));
        }
        if (!\is_subclass_of($_listener, Listener::class)) {
            throw new EventManagerExceptor('INVALID_EVENT_LISTENER', \compact('listener'));
        }

        return $_listener;
    }
}
