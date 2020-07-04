<?php
/**
 *启动swoole server
 * @date 2020.7.4 by Winst.
 */
namespace SwoolePool;

use SwoolePool\Core\RedisPoolSingleton;

define('ROOT_DIR', dirname(__DIR__) . '/' );
require_once __DIR__ . '/helper/autoload.php';

class HttpServer
{
    use SwooleServer;

    public function customWorkerStart($server, $worker_id)
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => 62157,
            'passwd' => 'e5Q0N3dfeS4h6c5R',
        ];
        //每个worker，启动redis连接池
        RedisPoolSingleton::getInstance($config);

    }

    public function custom($data)
    {
        $result = $this->dispatch($data['data']);
    }
}
$http_server = new HttpServer;
$http_server->run();
