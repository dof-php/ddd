<?php

declare(strict_types=1);

namespace DOF\DDD\Model;

use DOF\DDD\Model;

/**
 * @Title(Common Key-Title model)
 */
class KeyTitle extends Model
{
    /**
     * @Title(Key)
     * @Type(String)
     */
    protected $key;

    /**
     * @Title(Title)
     * @Type(String)
     */
    protected $title;
}
