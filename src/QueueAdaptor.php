<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\ETC;
use DOF\ENV;
use DOF\Tracer;
use DOF\Util\Task;
use DOF\Util\Format;
use DOF\Storage\Connection;
use DOF\Queue\Driver;
use DOF\Queue\Queuable;
use DOF\DDD\Exceptor\QueueAdaptorExceptor;

final class QueueAdaptor
{
    const QUEUE_DRIVER  = 'QUEUE_DRIVER';
    const QUEUE_DEFAULT = 'default';

    /** @var array: Class namespace <=> Queue storage instance */
    private static $pool = [];

    /**
     * Get a queuable from domain configurations and queue name
     *
     * @param string $driver: Name of queue driver
     * @param string $origin: One type of domain origin
     * @param string $name: Name of queue
     * @param \DOF\Tracer $tracer
     * @param bool $logging: Logging or not where query the driver
     * @param \DOF\Queue\Queuable|null
     */
    public static function get(string $driver, string $origin, string $name, Tracer $tracer = null, bool $logging = true) : Queuable
    {
        if ($instance = (self::$pool[$origin] ?? null)) {
            return $instance;
        }
        if (! ($queuable = Driver::get($driver))) {
            throw new QueueAdaptorExceptor('UNSUPPORTED_QUEUE_DRIVER', \compact('origin', 'driver'));
        }
        if (! ($config = ETC::final($origin, $driver))) {
            throw new QueueAdaptorExceptor('MISSING_QUEUE_DRIVER_CONFIGURATIONS', \compact('origin', 'driver'));
        }

        $option = Driver::option($driver, $name, $config);

        $instance = new $queuable($option, $logging);
        if ($tracer) {
            $tracer->trace($instance);
        }

        $instance->connector(function () use ($driver, $config, $option) {
            return Connection::get($driver, $config, $option);
        });

        return self::$pool[$origin] = $instance;
    }

    public static function enqueue(
        string $driver,
        Task $job,
        int $partition = 0,
        Tracer $tracer = null,
        string $prefix = null,
        string $domain = null
    ) {
        $origin = \get_class($job);

        $queue = self::name($origin, $partition, $prefix, $domain);

        $queuable = self::get($driver, $origin, $queue, $tracer);

        $queuable->enqueue($queue, $job);
    }

    public static function name(string $task, int $partition = 0, string $prefix = null, string $domain = null) : string
    {
        $class = Format::classname($task, true);

        if (ENV::final(($domain ?? $class), 'DISABLE_QUEUE_FORMATTING', false)) {
            return self::QUEUE_DEFAULT;
        }

        $key = $class ? \str_replace('\\', '_', $class) : self::QUEUE_DEFAULT;

        if ($partition > 0) {
            $key = \join('_', [$key, (\time() % $partition)]);
        }

        return \is_null($prefix) ? \strtolower($key) : \strtolower(\join(':', [$prefix, $key]));
    }
}
