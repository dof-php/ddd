<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Traits\WithTimestamp;

/**
 * @Title(Entity with timestamps)
 */
class EntityWithTS extends Entity
{
    use WithTimestamp;
}
