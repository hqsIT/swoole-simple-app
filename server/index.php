<?php
/**
 * Created by PhpStorm.
 * User: klinson <klinson@163.com>
 * Date: 18-5-31
 * Time: 上午10:22
 */

$serv = new swoole_server('0.0.0.0', 9501, SWOOLE_BASE, SWOOLE_SOCK_TCP);
$serv->set(array(
    'worker_num' => 2,
    'daemonize' => false,
    //'backlog' => 128,
));
//$serv->on('Connect', 'test');
$serv->on('Receive', function (swoole_server $server, int $fd, int $reactor_id, string $data) {
    var_dump($server);
    var_dump($fd);
    var_dump($reactor_id);
    var_dump($data);
});
$serv->on('Packet', function (swoole_server $server, string $data, array $client_info) {
    var_dump($server);
    var_dump($data);
    var_dump($client_info);
});


//$serv->on('Close', function () {
//
//});

$serv->start();