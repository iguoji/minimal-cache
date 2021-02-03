<?php
declare(strict_types=1);

namespace Minimal\Cache;

use PDOException;
use Swoole\Coroutine;
use Minimal\Pool\Cluster;
use Minimal\Support\Context;

/**
 * 管理类
 */
class Manager
{
    /**
     * 集群对象
     */
    protected Cluster $cluster;

    /**
     * 构造函数
     */
    public function __construct(array $configs, string $proxy = Proxy::class)
    {
        $this->cluster = new Cluster($configs, $proxy);
    }

    /**
     * 获取连接
     */
    public function connection(string $key = null, string $group = null) : Proxy
    {
        // 不论分组最后使用的连接缓存Key
        $lastTokenKey = sprintf('pool:cache:last');
        // 此分组最后使用的连接缓存Key
        $groupLastTokenKey = sprintf('pool:cache:last:%s', $group);

        // 按条件选择
        if (func_num_args() === 0 && Context::has($lastTokenKey)) {
            // 使用：不论分组最后使用的连接标识
            $token = Context::get($lastTokenKey);
        } else if (is_null($key) && Context::has($groupLastTokenKey)) {
            // 使用：当前分组最后使用的连接标识
            $token = Context::get($groupLastTokenKey);
        } else {
            // 使用：指定或默认的连接标识
            $token = sprintf('pool:cache:%s:%s', $group, $key ?? 'default');
        }

        // 保存标识
        Context::set($groupLastTokenKey, $token);
        Context::set($lastTokenKey, $token);

        // 存在连接、直接返回
        if (Context::has($token)) {
            return Context::get($token);
        }

        // 获取连接
        [$group, $key, $conn] = $this->cluster->get($group, $key);
        // 保存连接
        Context::set($token, $conn);
        // 归还连接
        Coroutine::defer(function() use($group, $key, $conn, $token){
            Context::del($token);
            $this->cluster->put($group, $key, $conn);
        });

        // 返回连接
        return $conn;
    }

    /**
     * 使用主写连接
     */
    public function master(string $key = null) : static
    {
        $this->connection($key, 'master');
        return $this;
    }

    /**
     * 使用从读连接
     */
    public function slave(string $key = null) : static
    {
        $this->connection($key, 'slave');
        return $this;
    }

    /**
     * 未知函数
     */
    public function __call(string $method, array $arguments) : mixed
    {
        // 获取连接
        $conn = $this->connection();
        // 返回结果
        return $conn->$method(...$arguments);
    }
}