<?php

declare(strict_types=1);

namespace DOF\DDD\Event;

use DOF\DDD\Event;
use DOF\DDD\Model;

final class ModelUpdated extends Event 
{
    protected $model;
    protected $diff;

    final public function getModel()
	{
		return $this->model;
	}

    final public function setModel(Model $model)
    {
        $this->model = $model;

        return $this;
    }

    final public function setDiff(array $diff)
    {
        $this->diff = $diff;

        return $this;
    }
}
