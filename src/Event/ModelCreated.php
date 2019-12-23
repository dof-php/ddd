<?php

declare(strict_types=1);

namespace DOF\DDD\Event;

use DOF\DDD\Event;
use DOF\DDD\Model;

final class ModelCreated extends Event 
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
