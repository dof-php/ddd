<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Traits\WithSoftDelete;

/**
 * @Title(Entity with soft delete)
 */
class EntityWithSD extends Entity
{
    use WithSoftDelete;
}
