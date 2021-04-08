<?php
declare(strict_types=1);

namespace Minimal\Cache\Driver;

use Minimal\Cache\Driver;

class File extends Driver
{
    /**
     * 构造函数
     */
    public function __construct(array $config)
    {
        // 保存配置
        $this->config = array_merge($this->getDefaultConfigStruct(), $config);
        // 创建连接
        $this->connect();
    }

    /**
     * 获取默认配置结构
     */
    public function getDefaultConfigStruct() : array
    {
        return [
            'path'          =>  '/tmp',
            'prefix'        =>  '',
            'expire'        =>  0,
            'serialize'     =>  'serialize',
            'data_compress' =>  false,
        ];
    }

    /**
     * 序列化数据
     */
    public function stringify(mixed $data) : string
    {
        switch ($this->config['serialize']) {
            case 'json':
                return json_encode($data);
                break;
            case 'base64':
                return base64_encode($data);
                break;
            default:
                return serialize($data);
                break;
        }
    }

    /**
     * 解析数据
     */
    public function parse(string $data) : mixed
    {
        switch ($this->config['serialize']) {
            case 'json':
                return json_decode($data, true);
                break;
            case 'base64':
                return base64_decode($datam);
                break;
            default:
                return unserialize($data);
                break;
        }
    }

    /**
     * 创建链接
     */
    public function connect(int $reconnect = 1) : mixed
    {
        return true;
    }

    /**
     * 释放连接
     */
    public function release() : void
    {
    }

    /**
     * 取得变量的存储文件名
     */
    public function getCacheKey(string $name) : string
    {
        $name = md5($name);
        $name = substr($name, 0, 2) . DIRECTORY_SEPARATOR . substr($name, 2);
        if (!empty($this->config['prefix'])) {
            $name = $this->config['prefix'] . DIRECTORY_SEPARATOR . $name;
        }
        return $this->config['path'] . $name . '.php';
    }

    /**
     * 获取缓存数据
     */
    protected function getRaw(string $key) : array|null
    {
        $filename = $this->getCacheKey($key);
        if (!is_file($filename)) {
            return null;
        }

        $content = @file_get_contents($filename);
        if (false === $content) {
            return null;
        }

        $expire = (int) substr($content, 8, 12);
        // 缓存过期删除缓存文件
        if (0 != $expire && time() - $expire > filemtime($filename)) {
            $this->unlink($filename);
            return null;
        }

        $content = substr($content, 32);

        // 启用数据压缩
        if ($this->config['data_compress'] && function_exists('gzcompress')) {
            $content = gzuncompress($content);
        }

        return ['content' => $content, 'expire' => $expire];
    }

    /**
     * 判断文件是否存在后，删除
     */
    private function unlink(string $path) : bool
    {
        try {
            return is_file($path) && unlink($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 删除文件夹
     */
    private function rmdir(string $dirname) : bool
    {
        if (!is_dir($dirname)) {
            return false;
        }

        $items = new \FilesystemIterator($dirname);

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $this->rmdir($item->getPathname());
            } else {
                $this->unlink($item->getPathname());
            }
        }

        @rmdir($dirname);

        return true;
    }

    /**
     * 判断数据
     */
    public function has(int|string $key) : bool
    {
        return $this->getRaw((string) $key) !== null;
    }

    /**
     * 读取数据
     */
    public function get(int|string $key, mixed $default = null) : mixed
    {
        $raw = $this->getRaw((string) $key);

        return is_null($raw) ? $default : $this->parse($raw['content']);
    }

    /**
     * 写入数据
     */
    public function set(int|string $key, mixed $value, int $expire = null) : bool
    {
        if (is_null($expire)) {
            $expire = $this->config['expire'];
        }

        $filename = $this->getCacheKey((string) $key);

        $dir = dirname($filename);

        if (!is_dir($dir)) {
            try {
                mkdir($dir, 0755, true);
            } catch (\Exception $e) {
                // 创建失败
            }
        }

        $data = $this->stringify($value);

        //数据压缩
        if ($this->config['data_compress'] && function_exists('gzcompress')) {
            $data = gzcompress($data, 3);
        }

        $data   = "<?php\n//" . sprintf('%012d', $expire) . "\n exit();?>\n" . $data;
        $result = file_put_contents($filename, $data);

        if ($result) {
            clearstatcache();
            return true;
        }

        return false;
    }

    /**
     * 自增数据
     */
    public function inc(int|string $key, int $step = 1) : int|bool
    {
        if ($raw = $this->getRaw((string) $key)) {
            $value  = $this->parse($raw['content']) + $step;
            $expire = $raw['expire'];
        } else {
            $value  = $step;
            $expire = 0;
        }

        return $this->set($key, $value, $expire) ? $value : false;
    }

    /**
     * 自减数据
     */
    public function dec(int|string $key, int $step = 1) : int|bool
    {
        return $this->inc($key, -$step);
    }

    /**
     * 删除数据
     */
    public function delete(int|string $key) : bool
    {
        return $this->unlink($this->getCacheKey((string) $key));
    }

    /**
     * 清空数据
     */
    public function clear() : bool
    {
        $dirname = $this->config['path'] . $this->config['prefix'];

        $this->rmdir($dirname);

        return true;
    }
}