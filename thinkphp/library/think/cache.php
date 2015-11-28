<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

class Cache
{
    /**
     * 操作句柄
     * @var object
     * @access protected
     */
    protected static $handler = null;

    /**
     * 连接缓存
     * @access public
     * @param array $options  配置数组
     * @return object
     */
    public static function connect($options = [])
    {
        $type          = !empty($options['type']) ? $options['type'] : 'File';
        $class         = 'think\\cache\\driver\\' . strtolower($type);
        self::$handler = new $class($options);
        return self::$handler;
    }

    public static function __callStatic($method, $params)
    {
        return call_user_func_array([self::$handler, $method], $params);
    }
}
