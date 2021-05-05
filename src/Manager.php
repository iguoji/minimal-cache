<?php
declare(strict_types=1);

namespace Minimal\Cache;

use Exception;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * 缓存管理类
 */
class Manager
{
    /**
     * 当前驱动
     */
    protected string $driver;

    /**
     * 当前代理
     */
    protected string $proxy;

    /**
     * 超时时间
     */
    protected float $timeout;

    /**
     * 连接池子
     */
    protected array $pool = [];

    /**
     * 构造函数
     */
    public function __construct(protected array $config, protected int $workerNum)
    {
        // 设置驱动
        $this->use($config['default'] ?? 'file');
    }

    /**
     * 切换驱动
     */
    public function use(string $name = null) : static
    {
        // 不存在配置
        if (!isset($this->config[$name])) {
            throw new Exception('cache ' . $name . ' driver config dont\'s exists');
        }

        // 当前驱动
        $this->driver = $name;
        // 当前代理
        if (isset($config[$this->driver]['proxy'])) {
            $this->proxy = $config[$this->driver]['proxy'];
        } else {
            $this->proxy = $config[$this->driver]['proxy'] ?? '\\Minimal\\Cache\\Driver\\' . ucwords($this->driver);
        }
        // 超时时间
        $this->timeout = $config[$this->driver]['timeout'] ?? 2;

        // 不存在连接则填充
        if (!isset($this->pool[$this->driver])) {
            $this->fill();
        }

        // 返回结果
        return $this;
    }

    /**
     * 填充连接
     */
    public function fill() : static
    {
        // 获取配置
        $config = $this->config[$this->driver];
        // 获取数量
        $size = max(1, (int) (($this->config['pool'] ?? swoole_cpu_num() * 10) / $this->workerNum));

        // 循环处理
        if (!isset($this->pool[$this->driver])) {
            $this->pool[$this->driver] = new Channel($size);
            for ($i = 0;$i < $size;$i++) {
                $proxyInterface = $this->proxy;
                $this->pool[$this->driver]->push(new $proxyInterface($config), $this->timeout);
            }
        }

        // 返回结果
        return $this;
    }

    /**
     * 获取连接
     */
    public function connection() : Driver
    {
        // 已有连接
        if (isset(Coroutine::getContext()['cache:connection'])) {
            return Coroutine::getContext()['cache:connection'];
        }

        // 获取连接
        $conn = $this->pool[$this->driver]->pop($this->timeout);
        if (false === $conn) {
            throw new Exception('很抱歉、缓存驱动繁忙！');
        }

        // 临时保存
        Coroutine::getContext()['cache:connection'] = $conn;
        // 记得归还
        Coroutine::defer(function() use($conn){
            $conn->release();
            $this->pool[$this->driver]->push($conn, $this->timeout);
        });
        // 返回连接
        return $conn;
    }

    /**
     * 未知函数
     */
    public function __call(string $method, array $arguments) : mixed
    {
        return $this->connection()->$method(...$arguments);
    }
}