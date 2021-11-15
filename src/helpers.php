<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

use Tphp\Basic\Tpl\Init as TplInit;
use Tphp\Basic\Tpl\Handle as TplHandle;
use Tphp\Basic\Tpl\Run as Run;

error_reporting(E_ALL ^ E_NOTICE);

if (!function_exists('apcu_fetch')) {
    /**
     * APCU扩展未安装方案（文件存储替代）
     * 推荐安装 http://pecl.php.net/package/APCu 扩展
     * @param $key
     * @return mixed|null
     */

    function apcu_fetch($key)
    {
        $cachePath = storage_path('framework/cache/apcu/' . md5($key));
        if (is_file($cachePath)) {
            return unserialize(import('XFile')->read($cachePath));
        } else {
            return null;
        }
    }

    function apcu_store($key, $var)
    {
        $cachePath = storage_path('framework/cache/apcu/' . md5($key));
        if (!is_file($cachePath)) {
            import('XFile')->write($cachePath, serialize($var));
        }
    }

    function apcu_clear_cache()
    {
        import('XFile')->deleteDir(storage_path('framework/cache/apcu/'));
    }
}

if (!function_exists('dump_parent')) {
    /**
     * 获取上一次方法调用路径
     */
    function dump_parent(...$args)
    {
        $db = debug_backtrace();
        if (count($db) > 3) {
            $db2 = $db[2];
            dump([
                'file' => $db2['file'],
                'func' => "{$db2['class']} -> {$db2['function']}",
                'line' => $db2['line'],
            ]);
        }

        count($args) > 0 && dump(...$args);
    }
}

if (!function_exists('help')) {
    /**
     * @see Run::help()
     */
    function help($class = null, $find = '', $type = 1)
    {
        return Run::help($class, $find, $type);
    }
}

if (!function_exists('set_ob_start')) {
    /**
     * @see Run::setObStart()
     */
    function set_ob_start($function = null, $keyName = '', $isBefore = false)
    {
        Run::setObStart($function, $keyName, $isBefore);
    }
}

if (!function_exists('get_ob_start_value')) {
    /**
     * @see Run::getObStartValue()
     */
    function get_ob_start_value($key = '')
    {
        return Run::getObStartValue($key);
    }
}

if (!function_exists('set_ob_start_value')) {
    /**
     * @see Run::setObStartValue()
     */
    function set_ob_start_value($config = null, $key = '', $isOverflow = false)
    {
        return Run::setObStartValue($config, $key, $isOverflow);
    }
}

if (!function_exists('plu')) {
    /**
     * @see \Tphp\Basic\Plugin\PluginClass::__init()
     */
    function plu($dir = '', $tpl = null, $isOnly = true)
    {
        return \Tphp\Basic\Plugin\PluginClass::__init($dir, $tpl, $isOnly);
    }
}

if (!function_exists('apcu')) {
    /**
     * @see \Tphp\Basic\Apcu::apcu()
     */
    function apcu($configs = [], $data = null)
    {
        return \Tphp\Basic\Apcu::__init()->apcu($configs, $data);
    }
}

if (!function_exists('tpl')) {
    /**
     * 显示Tpl代码
     * @param string $tpl Tpl模板路径
     * @param array $config 数据配置
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed|string
     */
    function tpl($tpl = '', $config = [], $isArray = false)
    {
        if (func_num_args() <= 0) {
            $tpl = false;
        }
        $gpp = \Tphp\Basic\Plugin\Init::getPluginPaths();
        $tmpPluginsTpl = $gpp['tpl'];
        $ret = TplHandle::start($tpl, $config, "run", $isArray);
        \Tphp\Config::$plugins['tpl'] = $tmpPluginsTpl;
        return $ret;
    }
}

if (!function_exists('page')) {
    /**
     * @see \Tphp\Basic\Sql\Page::page()
     */
    function page($type = null, $saveArgs = [], $fragment = '', $onEachSide = 0)
    {
        return (new \Tphp\Basic\Sql\Page())->page($type, $saveArgs, $fragment, $onEachSide);
    }
}

if (!function_exists('seo')) {
    /**
     * @see TplInit::setSeo();
     */
    function seo($config = null, $useBool = true)
    {
        if (is_null($config)) {
            return get_ob_start_value('seo');
        }
        return TplInit::setSeo($config, $useBool);
    }
}

if (!function_exists('__title')) {
    /**
     * @see TplInit::title();
     */
    function __title($str = null)
    {
        return TplInit::title($str);
    }
}

if (!function_exists('__keywords')) {
    /**
     * @see TplInit::keywords();
     */
    function __keywords($str = null)
    {
        return TplInit::keywords($str);
    }
}

if (!function_exists('__description')) {
    /**
     * @see TplInit::description();
     */
    function __description($str = null)
    {
        return TplInit::description($str);
    }
}

if (!function_exists('style')) {
    /**
     * @see Run::setStyleOrScript();
     */
    function style($scssCode = '', $prevMessage = '')
    {
        Run::setStyleOrScript($scssCode, $prevMessage);
    }
}

if (!function_exists('script')) {
    /**
     * @see Run::setStyleOrScript();
     */
    function script($jsCode = '', $prevMessage = '')
    {
        Run::setStyleOrScript($jsCode, $prevMessage, 'script');
    }
}

if (!function_exists('__exit')) {
    /**
     * 退出时保存Session
     * @param null $message
     * @see Run::exit()
     */
    function __exit($message = null)
    {
        Run::exit($message);
    }
}

if (!function_exists('__abort')) {
    /**
     * abort函数调用
     * @param int $code
     * @param string $message
     * @param array $headers
     * @see Run::abort()
     */
    function __abort($code = 404, $message = '', array $headers = [])
    {
        Run::abort($code, $message, $headers);
    }
}

if (!function_exists('__header')) {
    /**
     * http 头部设置
     * @param mixed ...$args
     * @see Run::header()
     */
    function __header(...$args)
    {
        Run::header(...$args);
    }
}

if (!function_exists('EXITJSON')) {
    /**
     * 空对象
     * @see Run::exitJson()
     */
    function EXITJSON()
    {
        Run::exitJson(func_get_args());
    }
}

if (!function_exists('import')) {
    /**
     * 获取import中的文件及参数传递
     * @return null
     * @see Run::import()
     */
    function import($importName = '', ...$args)
    {
        return Run::import($importName, $args);
    }
}

if (!function_exists('import_pointer')) {
    /**
     * 获取import中的文件及参数传递
     * 指针方式传递
     * @return null
     * @see Run::import()
     */
    function import_pointer($importName = '', &...$args)
    {
        return Run::import($importName, $args);
    }
}

if (!function_exists('is_function')) {
    /**
     * 判断是否是function类型
     * @param null $object
     * @return bool
     */
    function is_function($object = null)
    {
        if (empty($object) || is_string($object)) {
            return false;
        }

        return is_callable($object);
    }
}

if (!function_exists('json__encode')) {
    /**
     * json_encode转换，适用于HTML中的JSON参数传递
     * @param string $obj
     * @return mixed|string
     */
    function json__encode($obj = '')
    {
        if (empty($obj)) return '{}';
        if (is_array($obj) || is_object($obj)) {
            $obj = json_encode($obj, JSON_UNESCAPED_UNICODE);
        } else {
            $obj = trim($obj);
        }
        $obj = str_replace("&", "&amp;", $obj);
        $obj = str_replace("'", "&apos;", $obj);
        return $obj;
    }
}

if (!function_exists('__set_cookie')) {
    /**
     * 设置cookie
     * @param $name
     * @param string $value
     * @param int $expire
     */
    function __set_cookie($name, $value = "", $expire = 0)
    {
        if (empty($name)) {
            return;
        }
        if ($expire <= 0) {
            $expire = time() + 100 * 365 * 24 * 60 * 60;
        } else {
            $expire += time();
        }
        setcookie($name, $value, $expire, '/');
    }
}

if (!function_exists('__get_cookie')) {
    /**
     * 获取cookie
     * @param $name
     * @param string $value
     * @return string
     */
    function __get_cookie($name, $value = "")
    {
        if (empty($name)) {
            return "";
        }
        if (!isset($_COOKIE[$name])) {
            return $value;
        }
        return $_COOKIE[$name];
    }
}