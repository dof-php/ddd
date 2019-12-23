<?php

declare(strict_types=1);

namespace DOF\DDD;

/**
 * A special model which has identity
 *
 * @Title(A basic Entity)
 */
class Entity extends Model
{
    /**
     * @Title(Entity Identity)
     * @Type(Uint)
     */
    protected $id;

    final public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    final public function getId()
    {
        return $this->id;
    }

    public static function annotation()
    {
        return EntityManager::get(static::class);
    }
}
