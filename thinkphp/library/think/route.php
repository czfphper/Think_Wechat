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

class Route
{
    // 路由规则
    private static $rules = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'DELETE' => [],
        '*'      => [],
    ];

    // URL映射规则
    private static $map = [];
    // 子域名部署规则
    private static $domain = [];

    // 添加URL映射规则
    public static function map($map, $route = '')
    {
        if (is_array($map)) {
            self::$map = array_merge(self::$map, $map);
        } else {
            self::$map[$map] = $route;
        }
    }

    // 添加子域名部署规则
    public static function domain($domain, $rule = '')
    {
        if (is_array($domain)) {
            self::$domain = array_merge(self::$domain, $domain);
        } else {
            self::$domain[$domain] = $rule;
        }
    }

    // 注册路由规则
    public static function register($rule, $route = '', $type = '*', $option = [])
    {
        if (strpos($type, '|')) {
            foreach (explode('|', $type) as $val) {
                self::register($rule, $route, $val, $option);
            }
        } else {
            if (is_array($rule)) {
                foreach ($rule as $key => $val) {
                    if (0 === strpos($key, '[')) {
                        self::$rules[$type][substr($key, 1, -1)] = ['routes' => $val, 'option' => $option];
                    } else {
                        self::$rules[$type][$key] = ['route' => $val, 'option' => $option];
                    }
                }
            } else {
                if (0 === strpos($rule, '[')) {
                    self::$rules[$type][substr($rule, 1, -1)] = ['routes' => $route, 'option' => $option];
                } else {
                    self::$rules[$type][$rule] = ['route' => $route, 'option' => $option];
                }

            }
        }
    }

    // 路由分组
    public static function group($name, $routes = [], $type = '*', $option = [])
    {
        self::$rules[$type][$name] = ['routes' => $routes, 'option' => $option];
    }

    // 注册任意请求的路由规则
    public static function any($rule, $route = '', $option = [])
    {
        self::register($rule, $route, '*', $option);
    }

    // 注册get请求的路由规则
    public static function get($rule, $route = '', $option = [])
    {
        self::register($rule, $route, 'GET', $option);
    }

    // 注册post请求的路由规则
    public static function post($rule, $route = '', $option = [])
    {
        self::register($rule, $route, 'POST', $option);
    }

    // 注册put请求的路由规则
    public static function put($rule, $route = '', $option = [])
    {
        self::register($rule, $route, 'PUT', $option);
    }

    // 注册delete请求的路由规则
    public static function delete($rule, $route = '', $option = [])
    {
        self::register($rule, $route, 'DELETE', $option);
    }

    // 检测子域名部署
    public static function checkDomain()
    {
        // 开启子域名部署 支持二级和三级域名
        if (!empty(self::$domain)) {
            $rules = self::$domain;
            if (isset($rules[$_SERVER['HTTP_HOST']])) {
                // 完整域名或者IP配置
                $rule = $rules[$_SERVER['HTTP_HOST']];
            } else {
                // 子域名配置
                $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -2);
                if (!empty($domain)) {
                    $subDomain = implode('.', $domain);
                    $domain2   = array_pop($domain); // 二级域名
                    if ($domain) {
                        // 存在三级域名
                        $domain3 = array_pop($domain);
                    }
                    if ($subDomain && isset($rules[$subDomain])) {
                        // 子域名配置
                        $rule = $rules[$subDomain];
                    } elseif (isset($rules['*.' . $domain2]) && !empty($domain3)) {
                        // 泛三级域名
                        $rule      = $rules['*.' . $domain2];
                        $panDomain = $domain3;
                    } elseif (isset($rules['*']) && !empty($domain2)) {
                        // 泛二级域名
                        if ('www' != $domain2) {
                            $rule      = $rules['*'];
                            $panDomain = $domain2;
                        }
                    }
                }
            }
            if (!empty($rule)) {
                // 子域名部署规则
                // '子域名'=>'模块名'
                // '子域名'=>['模块名','var1=a&var2=b&var3=*'];
                if ($rule instanceof \Closure) {
                    // 执行闭包并中止
                    self::invokeRule($rule);
                    exit;
                }
                if (is_array($rule)) {
                    define('BIND_MODULE', $rule[0]);
                    //$_GET[VAR_MODULE] = $rule[0];
                    if (isset($rule[1])) {
                        // 传入参数
                        parse_str($rule[1], $parms);
                        if (isset($panDomain)) {
                            $pos = array_search('*', $parms);
                            if (false !== $pos) {
                                // 泛域名作为参数
                                $parms[$pos] = $panDomain;
                            }
                        }
                        $_GET = array_merge($_GET, $parms);
                    }
                } else {
                    define('BIND_MODULE', $rule);
                    //$_GET[VAR_MODULE] = $rule;
                }
            }
        }
    }

    // 检测URL路由
    public static function check($regx, $depr = '/')
    {
        // 优先检测是否存在PATH_INFO
        if (empty($regx)) {
            $regx = '/';
        }

        // 分隔符替换 确保路由定义使用统一的分隔符
        if ('/' != $depr) {
            $regx = str_replace($depr, '/', $regx);
        }
        if (isset(self::$map[$regx])) {
            // URL映射
            return self::parseUrl(self::$map[$regx]);
        }

        // 获取当前请求类型的路由规则
        $rules = self::$rules[REQUEST_METHOD];
        if (!empty(self::$rules['*'])) {
            // 合并任意请求的路由规则
            $rules = array_merge(self::$rules['*'], $rules);
        }
        // 路由规则检测
        if (!empty($rules)) {
            foreach ($rules as $rule => $val) {
                $option = $val['option'];
                // 请求类型检测
                if (isset($option['method']) && REQUEST_METHOD != strtoupper($option['method'])) {
                    continue;
                }
                // 伪静态后缀检测
                if (isset($option['ext']) && __EXT__ != $option['ext']) {
                    continue;
                }
                // https检测
                if (!empty($option['https']) && !self::isSsl()) {
                    continue;
                }
                // 自定义检测
                if (!empty($option['callback']) && is_callable($option['callback'])) {
                    if (false === call_user_func($option['callback'])) {
                        continue;
                    }
                }

                if (!empty($val['routes'])) {
                    // 分组路由
                    if (0 !== strpos($regx, $rule)) {
                        continue;
                    }
                    // 匹配到路由分组
                    foreach ($val['routes'] as $key => $route) {
                        if (is_numeric($key)) {
                            $key = array_shift($route);
                        }
                        $regx1 = substr($regx, strlen($rule) + 1);
                        if (0 === strpos($key, '/') && preg_match($key, $regx1, $matches)) {
                            // 检查正则路由
                            return self::checkRegx($route, $regx1, $matches);
                        } else {
                            // 检查规则路由
                            $result = self::checkRule($key, $route, $regx1);
                            if (false !== $result) {
                                return $result;
                            }
                        }
                    }
                } else {
                    if (is_numeric($rule)) {
                        $rule = array_shift($val);
                    }
                    // 单项路由
                    $route = $val['route'];
                    if (0 === strpos($rule, '/') && preg_match($rule, $regx, $matches)) {
                        return self::checkRegx($route, $regx, $matches);
                    } else {
                        // 规则路由
                        $result = self::checkRule($rule, $route, $regx);
                        if (false !== $result) {
                            return $result;
                        }
                    }
                }

            }
        }
        return false;
    }

    /**
     * 检查正则路由
     */
    private function checkRegx($route, $regx, $matches)
    {
        // 正则路由
        if ($route instanceof \Closure) {
            // 执行闭包
            $result = self::invokeRegx($route, $matches);
            // 如果返回布尔值 则继续执行
            return is_bool($result) ? $result : exit;
        }
        return self::parseRegex($matches, $route, $regx);
    }

    /**
     * 检查规则路由
     */
    private function checkRule($rule, $route, $regx)
    {
        $len1 = substr_count($regx, '/');
        $len2 = substr_count($rule, '/');
        if ($len1 >= $len2) {
            if ('$' == substr($rule, -1, 1)) {
                // 完整匹配
                if ($len1 != $len2) {
                    return false;
                } else {
                    $rule = substr($rule, 0, -1);
                }
            }
            if (false !== $match = self::match($regx, $rule)) {
                if ($route instanceof \Closure) {
                    // 执行闭包
                    $result = self::invokeRule($route, $match);
                    // 如果返回布尔值 则继续执行
                    return is_bool($result) ? $result : exit;
                }
                return self::parseRule($rule, $route, $regx);
            }
        }
        return false;
    }

    /**
     * 判断是否SSL协议
     * @return boolean
     */
    public static function isSsl()
    {
        if (isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))) {
            return true;
        } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
            return true;
        } else {
            return false;
        }
    }

    // 执行正则匹配下的闭包方法 支持参数调用
    private static function invokeRegx($closure, $var = [])
    {
        $reflect = new \ReflectionFunction($closure);
        $params  = $reflect->getParameters();
        $args    = [];
        array_shift($var);
        foreach ($params as $param) {
            $name = $param->getName();
            if (!empty($var)) {
                $args[] = array_shift($var);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            }
        }
        $reflect->invokeArgs($args);
    }

    // 执行规则匹配下的闭包方法 支持参数调用
    private static function invokeRule($closure, $var = [])
    {
        $reflect = new \ReflectionFunction($closure);
        $params  = $reflect->getParameters();
        $args    = [];
        foreach ($params as $param) {
            $name = $param->getName();
            if (isset($var[$name])) {
                $args[] = $var[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            }
        }
        $reflect->invokeArgs($args);
    }

    // 解析模块的URL地址 [模块/]控制器/操作
    public static function parseUrl($url)
    {
        if ('/' == $url) {
            return;
        }
        $paths = explode('/', $url);

        $module = defined('BIND_MODULE') ? BIND_MODULE : array_shift($paths);

        $controller = defined('BIND_CONTROLLER') ? BIND_CONTROLLER : array_shift($paths);

        $action = $paths ? array_shift($paths) : null;

        // 解析剩余的URL参数
        $var = [];
        if (!empty($paths)) {
            preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$var) {$var[strtolower($match[1])] = strip_tags($match[2]);}, implode('/', $paths));
        }
        $_GET = array_merge($var, $_GET);
        return [$module, $controller, $action];
    }

    // 解析规范的路由地址
    // 地址格式 [控制器/操作?]参数1=值1&参数2=值2...
    private static function parseRoute($url)
    {
        $var = [];
        if (false !== strpos($url, '?')) {
            // [控制器/操作?]参数1=值1&参数2=值2...
            $info = parse_url($url);
            $path = explode('/', $info['path']);
            parse_str($info['query'], $var);
        } elseif (strpos($url, '/')) {
            // [控制器/操作]
            $path = explode('/', $url);
        } else {
            // 参数1=值1&参数2=值2...
            parse_str($url, $var);
        }
        if (isset($path)) {
            $action     = array_pop($path);
            $action     = '[rest]' == $action ? REQUEST_METHOD : $action;
            $controller = !empty($path) ? array_pop($path) : null;
            $module     = !empty($path) ? array_pop($path) : null;
        }
        return ['route' => [$module, $controller, $action], 'var' => $var];
    }

    // 检测URL和规则路由是否匹配
    private static function match($regx, $rule)
    {
        $m1  = explode('/', $regx);
        $m2  = explode('/', $rule);
        $var = [];
        foreach ($m2 as $key => $val) {
            if (0 === strpos($val, ':')) {
                // 动态变量
                if (strpos($val, '\\')) {
                    $type = substr($val, -1);
                    if ('d' == $type && !is_numeric($m1[$key])) {
                        return false;
                    }
                    $name = substr($val, 1, -2);
                } elseif ($pos = strpos($val, '^')) {
                    $array = explode('|', substr(strstr($val, '^'), 1));
                    if (in_array($m1[$key], $array)) {
                        return false;
                    }
                    $name = substr($val, 1, $pos - 1);
                } else {
                    $name = substr($val, 1);
                }
                $var[$name] = $m1[$key];
            } elseif (0 !== strcasecmp($val, $m1[$key])) {
                return false;
            }
        }
        // 成功匹配后返回URL中的动态变量数组
        return $var;
    }

    // 解析规则路由
    // '路由规则'=>'[控制器/操作]?额外参数1=值1&额外参数2=值2...'
    // '路由规则'=> ['[控制器/操作]','额外参数1=值1&额外参数2=值2...']
    // '路由规则'=>'外部地址'
    // '路由规则'=>['外部地址','重定向代码']
    // 路由规则中 :开头 表示动态变量
    // 外部地址中可以用动态变量 采用 :1 :2 的方式
    // 'news/:month/:day/:id'=>['News/read?cate=1','status=1'],
    // 'new/:id'=>['/new.php?id=:1',301], 重定向
    private static function parseRule($rule, $route, $regx)
    {
        // 获取路由地址规则
        $url = is_array($route) ? $route[0] : $route;
        // 获取URL地址中的参数
        $paths = explode('/', $regx);
        // 解析路由规则
        $matches = [];
        $rule    = explode('/', $rule);
        foreach ($rule as $item) {
            if (0 === strpos($item, ':')) {
                // 动态变量获取
                if ($pos = strpos($item, '^')) {
                    $var = substr($item, 1, $pos - 1);
                } elseif (strpos($item, '\\')) {
                    $var = substr($item, 1, -2);
                } else {
                    $var = substr($item, 1);
                }
                $matches[$var] = array_shift($paths);
            } else {
                // 过滤URL中的静态变量
                array_shift($paths);
            }
        }
        if (0 === strpos($url, '/') || 0 === strpos($url, 'http')) {
            // 路由重定向跳转
            if (strpos($url, ':')) {
                // 传递动态参数
                $values = array_values($matches);
                $url    = preg_replace('/:(\d+)/e', '$values[\\1-1]', $url);
            }
            header("Location: $url", true, (is_array($route) && isset($route[1])) ? $route[1] : 301);
            exit;
        } else {
            // 解析路由地址
            $result = self::parseRoute($url);
            $var    = $result['var'];
            // 解析路由地址里面的动态参数
            $values = array_values($matches);
            foreach ($var as $key => $val) {
                if (0 === strpos($val, ':')) {
                    $var[$key] = $values[substr($val, 1) - 1];
                }
            }
            $var = array_merge($matches, $var);
            // 解析剩余的URL参数
            if (!empty($paths)) {
                preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$var) {
                    $var[strtolower($match[1])] = strip_tags($match[2]);
                }, implode('/', $paths));
            }
            // 解析路由自动传人参数
            if (is_array($route) && isset($route[1])) {
                parse_str($route[1], $params);
                $var = array_merge($var, $params);
            }
            $_GET = array_merge($var, $_GET);
            return $result['route'];
        }
    }

    // 解析正则路由
    // '路由正则'=>'[控制器/操作]?参数1=值1&参数2=值2...'
    // '路由正则'=>['[控制器/操作]?参数1=值1&参数2=值2...','额外参数1=值1&额外参数2=值2...']
    // '路由正则'=>'外部地址'
    // '路由正则'=>['外部地址','重定向代码']
    // 参数值和外部地址中可以用动态变量 采用 :1 :2 的方式
    // '/new\/(\d+)\/(\d+)/'=>['News/read?id=:1&page=:2&cate=1','status=1'],
    // '/new\/(\d+)/'=>['/new.php?id=:1&page=:2&status=1','301'], 重定向
    private static function parseRegex($matches, $route, $regx)
    {
        // 获取路由地址规则
        $url = is_array($route) ? $route[0] : $route;
        $url = preg_replace('/:(\d+)/e', '$matches[\\1]', $url);
        if (0 === strpos($url, '/') || 0 === strpos($url, 'http')) {
            // 路由重定向跳转
            header("Location: $url", true, (is_array($route) && isset($route[1])) ? $route[1] : 301);
            exit;
        } else {
            // 解析路由地址
            $result = self::parseRoute($url);
            $var    = $result['var'];
            // 解析剩余的URL参数
            $regx = substr_replace($regx, '', 0, strlen($matches[0]));
            if ($regx) {
                preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$var) {
                    $var[strtolower($match[1])] = strip_tags($match[2]);
                }, $regx);
            }
            // 解析路由自动传人参数
            if (is_array($route) && isset($route[1])) {
                parse_str($route[1], $params);
                $var = array_merge($var, $params);
            }
            $_GET = array_merge($var, $_GET);
            return $result['route'];
        }
    }
}
