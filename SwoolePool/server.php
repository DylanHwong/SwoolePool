<?php
/**
 *启动swoole server
 * @date 2020.7.4 by Winst.
 */
define('ROOT_DIR', dirname(__DIR__) . '/' );
require_once __DIR__ . '/helper/autoload.php';

class HttpServer
{
    use Swoole\SwooleServer;
    public function customWorkerStart($server, $worker_id)
    {
        //每个worker，启动redis连接池
        \Swoole\Core\RedisPool::getInstance();

    }

    public function custom($data)
    {
        $result = $this->dispatch($data['data']);
    }
}
$http_server = new HttpServer;
$http_server->run();
