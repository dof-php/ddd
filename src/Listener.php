<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Event;
use DOF\DDD\Listenable;

abstract class Listener extends Listenable
{
    /**
     * Event origin which listener listen on
     *
     * Could be null if current listener it's self is a pure task without any event trigger
     *
     * @Annotation(1)
     */
    protected $__EVENT__;

    final public function getEvent() : ?Event
    {
        return $this->__EVENT__;
    }

    final public function setEvent(Event $event)
    {
        $this->__EVENT__ = $event;

        return $this;
    }
}
