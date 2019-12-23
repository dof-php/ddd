<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Traits\WithTimestamp;
use DOF\DDD\Traits\WithSoftDelete;

/**
 * @Title(Model with timestamps and soft delete)
 */
class ModelWithTSSD extends Model
{
    use WithTimestamp;
    use WithSoftDelete;
}
