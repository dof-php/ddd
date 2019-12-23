<?php

declare(strict_types=1);

namespace DOF\DDD\Model;

use DOF\DDD\Model;

/**
 * @Title(Auth token classic structure)
 */
class AuthTokenClassic extends Model
{
    /**
     * @Title(User ID)
     * @Type(Pint)
     */
    protected $uid;

    /**
     * @Title(Auth Token)
     * @Type(String)
     */
    protected $token;
}
