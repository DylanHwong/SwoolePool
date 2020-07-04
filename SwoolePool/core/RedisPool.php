<?php
/**
 * 协程swoole连接池使用
 * 2020.07.02 by Winst.
 */
namespace Swoole\Core;

class RedisPool
{
    protected static $instance;
    protected $pool;
    protected $config;

    public function __construct($config = null)
    {
        go(function () use($config){
            $this->pool = new RedisPool((new RedisConfig)
                ->withHost($config['host'])
                ->withPort($config['port'])
                ->withAuth($config['passwd'])
                ->withDbIndex(0)
                ->withTimeout(1)
            );
        });
    }

    public static function getInstance($config = null)
    {
        if( empty(self::$instance) ) {
            if( empty($config) ) {
                throw new Exception('redis config empty');
            }
            self::$instance = new static($config);
        }
        return self::$instance;
    }

    public function getPool()
    {
        return $this->pool;
    }
}