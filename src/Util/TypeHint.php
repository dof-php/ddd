<?php

declare(strict_types=1);

namespace DOF\DDD\Util;

use DOF\DDD\Model;
use DOF\DDD\Entity;
use DOF\DDD\Util\IS;

class TypeHint extends \DOF\Util\TypeHint
{
    public static function entity($value) : bool
    {
        $entity = Entity::class;

        return $value === $entity || \is_subclass_of($value, $entity) || IS::entity($value);
    }

    public static function entitylist($value) : bool
    {
        if (! \is_array($value)) {
            return false;
        }

        foreach ($value as $key => $val) {
            if (! self::entity($val)) {
                return false;
            }
        }
    }

    public static function modellist($value) : bool
    {
        if (! \is_array($value)) {
            return false;
        }

        foreach ($value as $key => $val) {
            if (! self::model($val)) {
                return false;
            }
        }
    }

    public static function model($value) : bool
    {
        $model = Model::class;

        return $value === $model || \is_subclass_of($value, $model) || IS::model($value);
    }
}
