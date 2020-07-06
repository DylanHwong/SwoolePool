<?php
/**
 *启动swoole server
 * @date 2020.7.4 by Winst.
 */
namespace SwoolePool;

use Core\RedisPoolSingleton;
use Helper\Container;
use Src\SwooleServer;

require_once __DIR__ . '/vendor/autoload.php';

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
        return $this->dispatch($data);
    }
}
$http_server = new HttpServer;
$http_server->run();
