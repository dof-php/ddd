<?php

declare(strict_types=1);

namespace DOF\DDD\Model;

use DOF\DDD\Model;

/**
 * @Title(Fields of paginator structure)
 */
class Pagination extends Model
{
    /**
     * @Title(Total count of items in current query conditions)
     * @Type(Uint)
     */
    protected $total;

    /**
     * @Title(Count of items in current page)
     * @Type(Uint)
     */
    protected $count;

    /**
     * @Title(Current Page Number)
     * @Type(Pint)
     */
    protected $page;

    /**
     * @Title(Current Page Size)
     * @Type(Uint)
     */
    protected $size;
}
