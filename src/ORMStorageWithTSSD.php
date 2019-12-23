<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Traits\ORMWithTimestamp;
use DOF\DDD\Traits\ORMWithSoftDelete;

/**
 * ORM Storage with timestamps and soft delete
 */
class ORMStorageWithTSSD extends ORMStorageWithTS
{
    use ORMWithTimestamp;
    use ORMWithSoftDelete;
}
