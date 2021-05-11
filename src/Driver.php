<?php
declare(strict_types=1);

namespace Minimal\Cache;

abstract class Driver
{
    /**
     * 获取默认配置结构
     */
    abstract public function getDefaultConfigStruct() : array;

    /**
     * 创建连接
     */
    abstract public function connect(int $reconnect = 1) : mixed;

    /**
     * 释放连接
     */
    abstract public function release() : void;

    /**
     * 判断数据
     */
    abstract public function has(string|int $key) : bool;

    /**
     * 读取数据
     */
    abstract public function get(string|int $key, mixed $default = null) : mixed;

    /**
     * 写入数据
     */
    abstract public function set(string|int $key, mixed $value, int $expire = null) : bool;

    /**
     * 自增数据
     */
    abstract public function inc(string|int $key, int $step = 1) : int|bool;

    /**
     * 自减数据
     */
    abstract public function dec(string|int $key, int $step = 1) : int|bool;

    /**
     * 删除数据
     */
    abstract public function delete(string|int $key) : bool;

    /**
     * 清空数据
     */
    abstract public function clear() : bool;
}