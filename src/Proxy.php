<?php
declare(strict_types=1);

namespace Minimal\Cache;

use Redis;
use Throwable;
use RuntimeException;
use Minimal\Support\Arr;
use Minimal\Support\Context;

class Proxy
{
    /**
     * 驱动句柄
     */
    protected Redis $handle;

    /**
     * 配置信息
     */
    protected array $config;

    /**
     * 句柄标识
     */
    protected int $token;

    /**
     * 构造函数
     */
    public function __construct(array $config)
    {
        // 保存配置
        $this->config = Arr::array_merge_recursive_distinct($this->getDefaultConfigStruct(), $config);
        // 创建连接
        $this->connect();
        // 当前标志
        $this->token = Context::incr('cache:proxy');
    }

    /**
     * 句柄标识
     */
    public function getToken() : string
    {
        return 'Proxy# ' . $this->token;
    }

    /**
     * 获取默认配置结构
     */
    public function getDefaultConfigStruct() : array
    {
        return [
            'host'          =>  '127.0.0.1',
            'port'          =>  6379,
            'expire'        =>  0,
            'select'        =>  0,
            'password'      =>  '',
            'timeout'       =>  2,
            'options'       =>  [],
        ];
    }

    /**
     * 创建连接
     */
    public function connect(bool $reconnect = true) : Redis
    {
        try {
            // 创建驱动
            $this->handle = new Redis();
            // 连接驱动
            $this->handle->connect($this->config['host'], $this->config['port'],  $this->config['timeout']);
            // 设置密码
            if (!empty($this->config['password'])) {
                $this->handle->auth($this->config['password']);
            }
            // 选择数据库
            $this->handle->select($this->config['select']);
            // 设置选项
            foreach ($this->config['options'] as $key => $value) {
                $this->handle->setOption($key, $value);
            }
            // 返回驱动
            return $this->handle;
        } catch (Throwable $th) {
            // 尝试重连一次
            if ($reconnect) {
                return $this->connect(false);
            }
            throw $th;
        }
    }

    /**
     * 释放连接
     */
    public function release() : void
    {
    }

    /**
     * 是否存在
     */
    public function has(string $key) : bool
    {
        return (bool) $this->__call('exists', [$key]);
    }

    /**
     * 设置数据
     */
    public function set(string $key, int|float|bool|string $value, ?int $second = null) : bool
    {
        $second = $second ?? $this->config['expire'] ?? null;
        if (is_null($second) || $second === 0) {
            return true === $this->__call('set', [$key, $value]);
        } else {
            return true === $this->__call('setEx', [$key, $second, $value]);
        }
    }

    /**
     * 获取数据
     */
    public function get(string $key, mixed $defaultValue = null) : mixed
    {
        $value = $this->__call('get', [$key]);
        return false === $value ? $defaultValue : $value;
    }

    /**
     * 删除数据
     */
    public function delete(string $key) : bool
    {
        return (bool) $this->__call('del', [$key]);
    }

    /**
     * 未知函数
     */
    public function __call(string $method, array $arguments)
    {
        if (!method_exists($this->handle, $method)) {
            throw new RuntimeException(sprintf('Call to undefined method %s::%s()', $this->handle::class, $method));
        }
        return $this->handle->$method(...$arguments);
    }
}