<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Traits\WithTimestamp;

/**
 * @Title(Model with timestamps)
 */
class ModelWithTS extends Model
{
    use WithTimestamp;
}
