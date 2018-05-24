<?php

require_once __DIR__ . '/../vendor/autoload.php';

class Cache
{
    protected $cache;
    public static function instance($options = [])
    {
        $options = array_merge([
            'scheme' => 'tcp',
            'host'   => '127.0.0.1',
            'port'   => 6379,
        ], $options);
        return new static($options);
    }
    protected function __construct($options)
    {
        $this->cache = new \Predis\Client($options);
    }


    public function set($key, $value)
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $this->cache->set($key, $value);
    }

    public function get($key, $default = null)
    {
        $value = $this->cache->get($key);
        if (is_null($value)) {
            return $default;
        } else {
            if (is_array($array = json_decode($value, true))) {
                return $array;
            } else {
                return $value;
            }
        }
    }
}