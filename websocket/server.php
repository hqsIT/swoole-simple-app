<?php
require_once __DIR__.'/../helpers/Cache.php';

class WebSocketTest {
    public $cache;
    public $redis;
    public function __construct() {
        $this->cache = Cache::instance();
        $this->server = new swoole_websocket_server("127.0.0.1", 1099);

        $this->server->set([
            // 静态资源服务器
            'enable_static_handler' => true,
            'document_root' => './document',

            // 后台启动进程
            //'daemonize' => true,

            // 日志等级
            //'log_level' => 5,
        ]);

        // 设置onHandShake回调函数后不会再触发onOpen事件，需要应用代码自行处理
        //$this->server->on('handshake', array($this, 'onHandShake'));

        $this->server->on('open', function (swoole_websocket_server $server, $request) {
            //echo $request->header['sec-websocket-key'] . PHP_EOL;
            print_r($server->connection_info($request->fd));
            $connections = $this->cache->get('connections', []);
            if (empty($connections)) {
                $connections = [];
            }
            $item = [
                'fd' => $request->fd,
                'address' => $request->server['remote_addr'],
                'port' => $request->server['remote_port']
            ];
            $connections[$item['fd']] = $item;
            $this->cache->set('connections' ,$connections);

            $this->sendAll("{$item['address']}:{$item['port']} 已连接成功");

            echo "server: handshake success with fd $request->fd\n";
        });

        $this->server->on('message', function (swoole_websocket_server $server, $frame) {
            echo "receive from {$frame->fd}, opcode:{$frame->opcode}, finish:{$frame->finish}, data:\n{$frame->data}\n";

            //$server->push($frame->fd, json_encode([
            //    'message' => 'I had receive !',
            //    'data' => $frame->data
            //]));

            $connections = $this->cache->get('connections', []);

            $this->sendAll("{$connections[$frame->fd]['address']}:{$connections[$frame->fd]['port']}: {$frame->data}");
        });

        $this->server->on('close', function ($ser, $fd) {
            echo "client {$fd} closed\n";

            $connections = $this->cache->get('connections', []);
            $connection = $connections[$fd];
            unset($connections[$fd]);
            $this->cache->set('connections' ,$connections);

            $this->sendAll("{$connection['address']}:{$connection['port']} 已断开连接");
        });

        $this->server->on('request', function (swoole_http_request $request, swoole_http_response $response) {
            // 接收http请求从get获取message参数的值，给用户推送
            // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
            //foreach ($this->server->connections as $fd) {
            //    $this->server->push($fd, $request->get['message']);
            //}

            echo "receive a http request\n";
            //print_r($request);
            //print_r($response);

            $response->end();
        });

        $this->server->start();
    }

    public function sendAll($message)
    {
        $connections = $this->cache->get('connections', []);
        var_dump($connections);
        foreach ($connections as $connection) {
            $this->server->push($connection['fd'], $message);
        }
    }


    public function onHandShake(swoole_http_request $request, swoole_http_response $response)
    {
        // print_r( $request->header );
        // if (如果不满足我某些自定义的需求条件，那么返回end输出，返回false，握手失败) {
        //    $response->end();
        //     return false;
        // }

        // websocket握手连接算法验证
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
            $response->end();
            return false;
        }
        echo $request->header['sec-websocket-key'];
        $key = base64_encode(sha1(
            $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        ));

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
        ];

        // WebSocket connection to 'ws://127.0.0.1:9502/'
        // failed: Error during WebSocket handshake:
        // Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket
        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        $response->status(101);
        $response->end();
        echo "connected!" . PHP_EOL;
        return true;
    }
}
new WebSocketTest();