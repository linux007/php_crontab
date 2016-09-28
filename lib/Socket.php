<?php

/**
 * Created by PhpStorm.
 * User: yuyc
 * Date: 2016/9/23
 * Time: 18:55
 */
class UdpDispach {

    public function send()  {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_connect($socket, "255.255.255.255", 8251);
//        $allIP = swoole_get_local_ip();
        $data = json_encode([
            'flags' => 'dispatch',
//            'data' => [
//                'ip' => $allIP['eth0'],
//            ]
        ]);
        socket_write($socket, $data, strlen($data));
        socket_close($socket);
    }
}
