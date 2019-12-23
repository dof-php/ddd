<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Traits\ORMWithTimestamp;

/**
 * ORM Storage with timestamps
 */
class ORMStorageWithTS extends ORMStorage
{
    use ORMWithTimestamp;
}
