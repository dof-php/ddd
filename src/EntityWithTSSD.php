<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Traits\WithTimestamp;
use DOF\DDD\Traits\WithSoftDelete;

/**
 * @Title(Entity with timestamps and soft delete)
 */
class EntityWithTSSD extends Entity
{
    use WithTimestamp;
    use WithSoftDelete;
}
