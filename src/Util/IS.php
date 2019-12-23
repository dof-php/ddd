<?php

declare(strict_types=1);

namespace DOF\DDD\Util;

use DOF\DDD\Model;
use DOF\DDD\Entity;

class IS extends \DOF\Util\IS 
{
    /**
     * Check if two objects and classes has difference or not
     */
    public static function diff($origin1, $origin2) : bool
    {
        if ((! \is_object($origin1)) || (! \is_object($origin2))) {
            return true;
        }
        if (\get_class($origin1) !== \get_class($origin2)) {
            return true;
        }

        if (($origin1 instanceof Entity) && ($origin2 instanceof Entity)) {
            return $origin1->getPk() !== $origin2->getPk();
        }

        if (($origin1 instanceof Model) && ($origin2 instanceof Model)) {
            // Diff All Properties of origins
            return Model::diff($origin1, $origin2, []) ? true : false;
        }

        return $origin1 != $origin2;
    }

    public static function model($value) : bool
    {
        if (self::entity($value)) {
            return true;
        }

        return $value && ((\is_object($value) && ($value instanceof Model)) || (\is_string($value)) && \is_subclass_of($value, Model::class));
    }

    public static function entity($value) : bool
    {
        return $value && ((\is_object($value) && ($value instanceof Entity)) || (\is_string($value)) && \is_subclass_of($value, Entity::class));
    }
}
