<?php

declare(strict_types=1);

namespace DOF\DDD\Event;

use DOF\DDD\Event;
use DOF\DDD\Entity;

final class EntityUpdated extends Event 
{
    protected $entity;
    protected $diff;

    final public function getEntity()
	{
		return $this->entity;
	}

    final public function setEntity(Entity $entity)
    {
        $this->entity = $entity;

        return $this;
    }

    final public function setDiff(array $diff)
    {
        $this->diff = $diff;

        return $this;
    }
}
