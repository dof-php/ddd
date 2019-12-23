<?php

declare(strict_types=1);

namespace DOF\DDD\Util;

use DOF\Util\Exceptor\TypeCastExceptor;
use DOF\DDD\Model;
use DOF\DDD\Entity;
use DOF\DDD\Util\TypeHint;

class TypeCast extends \DOF\Util\TypeCast
{
    public static function entity($value, bool $force = false, string $type = 'entity') : Entity
    {
        if (TypeHint::entity($value)) {
            return $value;
        }

        throw new TypeCastExceptor('TYPECAST_FAILED', \compact('value', 'force', 'type'));
    }

    public static function model($value, bool $force = false, string $type = 'model') : Model
    {
        if (TypeHint::model($value)) {
            return $value;
        }

        throw new TypeCastExceptor('TYPECAST_FAILED', \compact('value', 'force', 'type'));
    }
}
