<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\Traits\ORMWithSoftDelete;

/**
 * ORM Storage with soft delete
 */
class ORMStorageWithSD extends ORMStorage
{
    use ORMWithSoftDelete;
}
