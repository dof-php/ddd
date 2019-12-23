<?php

declare(strict_types=1);

namespace DOF\DDD\Traits;

trait ORMWithTimestamp
{
    /**
     * @Column(deleted_at)
     * @Type(int)
     * @Length(10)
     * @Unsigned(1)
     * @Notnull(1)
     * @Default(0)
     */
    protected $deletedAt;

    /**
     * @Column(is_deleted)
     * @Type(tinyint)
     * @Length(1)
     * @Unsigned(1)
     * @Notnull(1)
     * @Default(0)
     */
    protected $isDeleted;
}
