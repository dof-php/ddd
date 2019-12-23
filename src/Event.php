<?php

declare(strict_types=1);

namespace DOF\DDD;

use Throwable;
use DOF\ENV;
use DOF\Util\IS;
use DOF\Util\Task;
use DOF\Util\Format;
use DOF\DDD\Listener;
use DOF\DDD\QueueAdaptor;
use DOF\DDD\Exceptor\EventExceptor;
use DOF\DDD\Event\ModelCreated;
use DOF\DDD\Event\ModelRemoved;
use DOF\DDD\Event\ModelUpdated;
use DOF\DDD\Event\EntityCreated;
use DOF\DDD\Event\EntityRemoved;
use DOF\DDD\Event\EntityUpdated;

/**
 * Notes:
 * - Event properties must un-private
 *
 * - No exception should be thrown when handling async event
 *   Since it will broken the execution of business code
 *   We logging all errors and exceptions happens in queuing process
 */
abstract class Event implements Task
{
    use \DOF\Traits\Tracker;
    use \DOF\Traits\ObjectData;
    use \DOF\Traits\ExceptorThrow;

    const QUEUE_PREFIX = 'event';
    const QUEUE_DRIVER = 'EVENT_QUEUE_DRIVER';
    const ASYNC_OPTION = 'ASYNC_EVENT';

    /**
     * Dynamic listener appending and removing
     *
     * @Annotation(1)
     * @var array
     */
    protected $__LISTENER__ = [];

    final public function listen(...$listeners)
    {
        foreach ($listeners as $listener) {
            if (\is_string($listener) && \class_exists($listener) && \is_subclass_of($listener, Listener::class)) {
                $this->__LISTENER__[$listener] = true;
            } else {
                throw new EventExceptor('INVALID_EVENT_LISTENER_TO_APPEND', \compact('listener'));
            }
        }

        return $this;
    }

    final public function unlisten(...$listeners)
    {
        foreach ($listeners as $listener) {
            if (\is_string($listener) && \class_exists($listener) && \is_subclass_of($listener, Listener::class)) {
                unset($this->__LISTENER__[$listener]);
            } else {
                throw new EventExceptor('INVALID_EVENT_LISTENER_TO_REMOVE', \compact('listener'));
            }
        }

        return $this;
    }

    /**
     * @param bool $instant: Execute event instantly or not - enqueue job and process background
     */
    final public function publish(bool $instant = false)
    {
        if (! $instant) {
            $event = $domain = static::class;

            switch ($event) {
                case EntityCreated::class:
                case EntityRemoved::class:
                case EntityUpdated::class:
                    $domain = \get_class($this->getEntity());
                    break;
                case ModelCreated::class:
                case ModelRemoved::class:
                case ModelUpdated::class:
                    $domain = \get_class($this->getModel());
                    break;
                 default:
                    break;
            }

            $async = ENV::final($domain, self::ASYNC_OPTION, []);
            if ($async) {
                $partition = $async[$event] ?? (\in_array($event, $async) ? 0 : null);
                if ((null !== $partition) && (false !== $partition)) {
                    $partition = ($partition === true) ? 0 : $partition;
                    if (\is_int($partition)) {
                        $driver = ENV::finalMatch($domain, [self::QUEUE_DRIVER, QueueAdaptor::QUEUE_DRIVER]);
                        if (IS::empty($driver)) {
                            $this->logger()->log(
                                'event-error',
                                'Empty async event queue driver, execute event synchronously',
                                \compact('event', 'domain', 'partition')
                            );
                        } else {
                            try {
                                QueueAdaptor::enqueue($driver, $this, $partition, $this->tracer(), self::QUEUE_PREFIX, $domain);
                                return;
                            } catch (Throwable $th) {
                                $this->logger()->log(
                                    'event-error',
                                    'Enqueue event job error, execute event synchronously',
                                    Format::throwable($th)
                                );
                            }
                        }
                    } else {
                        $this->logger()->log(
                            'event-error',
                            'Invalid async event queue partition integer, execute event synchronously',
                            \compact('event', 'partition', 'domain')
                        );
                    }
                }
            }
        }

        // Let exceptions happen if we execute event job synchronously
        // try {
        if (IS::confirm(self::annotation()['meta']['STANDALONE'] ?? null)) {
            $this->execute();
        } else {
            $this->broadcast();
        }
        // } catch (Throwable $th) {
            // $this->logger()->log('event-execution-exception', $event, Format::throwable($th));
        // }
    }

    /**
     * Broadcast event as queue job
     *
     * This method can be overrode if event class does not need broadcasting
     */
    public function execute()
    {
        $this->broadcast();
    }

    final private function broadcast(bool $instant = false)
    {
        try {
            foreach ($this->__LISTENER__ as $listener => $status) {
                $this->di($listener)->setEvent($this)->handle($instant);
            }
        } catch (Throwable $th) {
            $this->logger()->log(
                'event-listener-handle-exception',
                ['event' => static::class, 'listener' => $listener, 'type' => 'dynamic'],
                Format::throwable($th)
            );
        }

        try {
            foreach ((self::annotation()['meta']['LISTENER'] ?? []) as $listener) {
                $this->di($listener)->setEvent($this)->handle($instant);
            }
        } catch (Throwable $th) {
            $this->logger()->log(
                'event-listener-handle-exception',
                ['event' => static::class, 'listener' => $listener, 'type' => 'annotation'],
                Format::throwable($th)
            );
        }
    }

    final public static function annotation()
    {
        return EventManager::get(static::class);
    }
}
