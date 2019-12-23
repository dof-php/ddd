<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Traits\WithSoftDelete;

/**
 * @Title(Model with soft delete)
 */
class ModelWithSD extends Model
{
    use WithSoftDelete;
}
