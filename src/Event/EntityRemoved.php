<?php

declare(strict_types=1);

namespace DOF\DDD\Event;

use DOF\DDD\Event;
use DOF\DDD\Entity;

final class EntityRemoved extends Event 
{
    protected $entity;

    final public function getEntity()
	{
		return $this->entity;
	}

    final public function setEntity(Entity $entity)
    {
        $this->entity = $entity;

        return $this;
    }
}
