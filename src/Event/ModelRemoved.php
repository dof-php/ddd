<?php

declare(strict_types=1);

namespace DOF\DDD\Event;

use DOF\DDD\Model;
use DOF\DDD\Event;

final class ModelRemoved extends Event
{
    protected $model;

    final public function getModel()
	{
		return $this->model;
	}

    final public function setModel(Model $model)
    {
        $this->model = $model;

        return $this;
    }
}
