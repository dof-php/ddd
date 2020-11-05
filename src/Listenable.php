<?php

declare(strict_types=1);

namespace DOF\DDD;

use Throwable;
use DOF\ENV;
use DOF\DMN;
use DOF\Util\IS;
use DOF\Util\Format;
use DOF\Util\Task;
use DOF\DDD\QueueAdaptor;

abstract class Listenable implements Task
{
    use \DOF\Traits\Tracker;
    use \DOF\Traits\ObjectData;
    use \DOF\Traits\ExceptorThrow;
    use \DOF\DDD\Traits\Cachable;

    const QUEUE_PREFIX = 'listener';
    const QUEUE_DRIVER = 'LISTENER_QUEUE_DRIVER';
    const ASYNC_OPTION = 'ASYNC_LISTENER';

    final public function handle(bool $instant = false)
    {
        if (! $instant) {
            $listener = static::class;

            $async = ENV::final($listener, self::ASYNC_OPTION, []);
            if ($async) {
                $partition = $async[$listener] ?? (\in_array($listener, $async) ? 0 : null);
                if ((null !== $partition) && (false !== $partition)) {
                    $partition = ($partition === true) ? 0 : $partition;
                    if (\is_int($partition)) {
                        $driver = ENV::finalMatch($listener, [self::QUEUE_DRIVER, QueueAdaptor::QUEUE_DRIVER]);
                        if (IS::empty($driver)) {
                            $this->logger()->log(
                                'listener-error',
                                'Empty async listener queue driver, execute listener synchronously',
                                \compact('listener', 'driver')
                            );
                        } else {
                            try {
                                QueueAdaptor::enqueue($driver, $this, $partition, $this->tracer(), self::QUEUE_PREFIX);
                                return;
                            } catch (Throwable $th) {
                                $this->logger()->log(
                                    'listener-error',
                                    'Enqueue listener job error, execute listener synchronously',
                                    Format::throwable($th)
                                );
                            }
                        }
                    } else {
                        $this->logger()->log(
                            'listener-error',
                            'Invalid async listener partition integer, execute listener synchronously',
                            \compact('partition')
                        );
                    }
                }
            }
        }

        // Let exceptions happen if we execute listener job synchronously
        // try {
        $this->execute();
        // } catch (Throwable $th) {
            // $this->logger()->log('listener-handle-exception', $listener, Format::throwable($th));
        // }
    }

    abstract public function execute();
}
