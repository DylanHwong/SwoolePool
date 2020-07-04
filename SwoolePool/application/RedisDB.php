<?php
/**
 * redis 操作类
 * @date 2020.01.04 by Winst.
 */
namespace Application;

use RuntimeException;
use SwoolePool\Core\RedisPoolSingleton;

class RedisDB
{
    private $pool;
    private $redis;

    public function __construct()
    {
        //获取连接池对象，取出redis连接
        $this->pool = RedisPoolSingleton::getInstance()->getPool();
        $this->redis = $this->pool->get();
    }

    public function set($key, $value)
    {
        return $this->redis->set($key, $value);
    }

    public function get($key)
    {
        return $this->redis->get($key);
    }

    public function lPush($key, $value)
    {
        return $this->redis->lpush($key, $value);
    }

    public function rPop($key)
    {
        return $this->redis->rpop($key);
    }

    public function hSet($key, $field, $value)
    {
        return $this->redis->hset($key, $field, $value);
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
        //归还redis连接到连接池
        $this->pool->put($this->redis);
    }
}