<?php
declare(strict_types=1);

namespace Minimal\Cache;

/**
 * 缓存管理类
 */
class Manager
{
    /**
     * 驱动列表
     */
    protected array $drivers;

    /**
     * 构造函数
     */
    public function __construct(protected array $config)
    {}

    /**
     * 获取仓库
     */
    public function store(string $name = null) : Driver
    {
        $driver = $name ?? $this->config['handle'];

        if (!isset($this->drivers[$driver])) {
            $class = \Minimal\Cache\Driver\File::class;
            switch ($driver) {
                case 'redis':
                    $class = \Minimal\Cache\Driver\Redis::class;
                    break;
                default:
                break;
            }
            $this->drivers[$driver] = new $class($this->config[$driver]);
        }

        return $this->drivers[$driver];
    }

    /**
     * 未知函数
     */
    public function __call(string $method, array $arguments) : mixed
    {
        return $this->store()->$method(...$arguments);
    }
}