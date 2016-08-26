<?php
/**
 * Created by PhpStorm.
 * User: yuyc
 * Date: 2016/8/25
 * Time: 10:48
 */
class Worker {

    public $server = null;
    public $workerId;

    public function __construct(swoole_server $server, $workerId)
    {
        $this->server = $server;
        $this->workerId = $workerId;
    }



}