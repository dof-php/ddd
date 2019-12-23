<?php

declare(strict_types=1);

namespace DOF\DDD\Entity;

use DOF\DDD\Entity;

/**
 * @Title(Common network request Logging)
 */
class RequestLog extends Entity
{
    /**
     * @Title(Created Timestamp)
     * @Type(Uint)
     */
    protected $at;

    /**
     * @Title(API called by the action: http/cli/...)
     * @Type(String)
     */
    protected $api;

    /**
     * @Title(Operator ID performed the action)
     * @Type(Int)
     */
    protected $operatorId;

    /**
     * @Title(Operator IDs indirectly performed the action)
     * @Type(String)
     * @Notes(Separated with comma)
     */
    protected $operatorIdsForward;

    /**
     * @Title(Action title)
     * @Type(String)
     */
    protected $title;

    /**
     * @Title(Action value, vary from api type)
     * @Type(String)
     */
    protected $actionValue;

    /**
    * @Title(Action type, vary from api type)
    * @Type(String)
    */
    protected $actionType;

    /**
     * @Title(Class namespace owns server business logic)
     * @Type(String)
     */
    protected $class;

    /**
     * @Title(Class method owns server business logic)
     * @Type(String)
     */
    protected $method;

    /**
     * @Title(Client IP performed the action)
     * @Type(String)
     */
    protected $clientIP;

    /**
     * @Title(Client IPs of HTTP agents indirectly performed the action)
     * @Type(String)
     * @Notes(Separated with comma)
     */
    protected $clientIPsForward;

    /**
     * @Title(Client operating system performed the action)
     * @Type(String)
     */
    protected $clientOS;

    /**
     * @Title(Client name performed the action)
     * @Type(String)
     */
    protected $clientName;

    /**
     * @Title(Client info performed the action)
     * @Type(String)
     */
    protected $clientInfo;

    /**
     * @Title(Client port performed the action)
     * @Type(Uint)
     */
    protected $clientPort;

    /**
     * @Title(Server IP response the action)
     * @Type(String)
     */
    protected $serverIP;

    /**
     * @Title(Server operating system response the action)
     * @Type(String)
     */
    protected $serverOS;

    /**
     * @Title(Server name response the action)
     * @Type(String)
     */
    protected $serverName;

    /**
     * @Title(Server info response the action)
     * @Type(String)
     */
    protected $serverInfo;

    /**
     * @Title(Server port response the action)
     * @Type(Uint)
     */
    protected $serverPort;

    /**
     * @Title(Server status code after responsing the action)
     * @Type(Int)
     */
    protected $serverStatus;

    /**
     * @Title(Server business logic occur errors or not during responsing the action)
     * @Type(Bool)
     */
    protected $serverError;
}
