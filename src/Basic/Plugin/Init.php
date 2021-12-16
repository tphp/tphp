<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

/**
 * 插件功能
 */

namespace Tphp\Basic\Plugin;

use Tphp\Basic\Tpl\Init as TplInit;
use Tphp\Basic\Sql\SqlCache;
use Tphp\Basic\Tpl\Handle as TplHandle;
use Tphp\Basic\Tpl\Run;
use Tphp\Register;
use Tphp\Config as TphpConfig;

class Init
{
    /**
     * 获取错误路径调用信息
     * @param String $path
     * @param String $type
     * @return string
     */
    private static function getPathErrorString(String $path, String $type)
    {
        $from = '';
        $pos = strpos($path, "=");
        if ($pos > 0) {
            $from = substr($path, $pos);
            $path = substr($path, 0, $pos);
        }
        if (!empty($from)) {
            $from = trim($from, '=');
        }
        if (!empty($from)) {
            $from = "<pre>From: {$from}</pre>";
        }
        return "<pre>{$type}: {$path} Is Not Found!</pre>{$from}";

    }

    /**
     * 分析字符串中的@符号
     * @param string $str
     * @return array
     */
    private static function getStaticFlag($str = '', $topFlag = '')
    {
        $str = trim($str);
        if (empty($str)) {
            return ["", ""];
        }
        $flag = "";
        $strLen = strlen($str);
        $pos = -1;
        for ($i = 0; $i < $strLen; $i++) {
            if ($str[$i] !== '@') {
                break;
            }
            $flag .= '@';
            $pos = $i;
        }
        if ($pos >= 0) {
            $str = substr($str, $pos + 1);
        }
        $flag .= $topFlag;
        $flag = trim($flag);
        if (!empty($flag)) {
            $flag = '@@';
        }
        return [$flag, $str];
    }

    /**
     * 获取插件路径，系统路径或自定义路径，系统路径优先
     * @param string $baseDir
     * @param string $type
     * @return string
     */
    public static function getPluginDir($baseDir = '', $type = 'base')
    {
        $path = '';
        if (empty($type)) {
            return $path;
        }

        // js或css合并在视图中
        if (in_array($type, ['sjs', 'scss'])) {
            $type = 'base';
        }

        return Register::getViewPath("plugins/{$baseDir}/{$type}");
    }

    /**
     * 动态加载 scss 和 sjs 文件
     * @param $baseDir
     * @param $dir
     * @param $type
     */
    private static function getStaticHandle($baseDir, $dir, $type = 'static')
    {
        $baseDir = trim($baseDir, "/ ");
        if (empty($baseDir)) {
            return;
        }
        $dirArr = explode("/", $baseDir);
        if (count($dirArr) !== 2) {
            return;
        }
        list($top, $sub) = $dirArr;
        if (empty(trim($top)) || empty(trim($sub))) {
            return;
        }

        if ($type !== 'static') {
            if ($type == 'css') {
                $type = 'scss';
            } elseif ($type != 'js') {
                return;
            }
            list($isOnly, $path, $pathDefault) = self::getPath($dir, $baseDir);
            if (!empty($pathDefault)) {
                $baseDir = $pathDefault;
            }
            TplHandle::setPluginsStaticPaths($baseDir, $path, $type, true);
            return;
        }

        $pos = strpos($dir, "/");
        if ($pos === false) {
            return;
        }

        $firstDir = substr($dir, 0, $pos);
        if ($firstDir === 'scss') {
            $type = 'scss';
        } elseif ($firstDir === 'sjs') {
            $type = 'js';
        } else {
            return;
        }

        $dir = substr($dir, $pos + 1);
        $pos = strrpos($dir, ".");
        if ($pos > 0) {
            $ext = substr($dir, $pos + 1);
            if (!empty($ext)) {
                if ($firstDir === 'scss') {
                    if (in_array($ext, ['scss', 'css'])) {
                        $dir = substr($dir, 0, $pos);
                    }
                } elseif ($ext === 'js') {
                    $dir = substr($dir, 0, $pos);
                }
            }
        }
        TplHandle::setPluginsStaticPaths($baseDir, $dir, $type);
    }

    /**
     * 获取插件文件路径 plugins static 文件夹
     * @param null $url 指定路径
     * @param bool $isDomain 是否显示域名或IP地址
     * @param string $topDir 默认路径
     * @param string $type js css static
     * @return array|string
     */
    public static function getStatic($url = null, $isDomain = true, $type = 'static', $topDir = '')
    {
        $pluginsDir = "/static/plugins/";

        $urlPath = "";
        if (is_string($isDomain)) {
            $urlPath = trim($isDomain);
            $urlPath = rtrim($isDomain, "\\/");
        } else if (is_bool($isDomain) && $isDomain) {
            $urlPath = TplHandle::getUrl('');
        }

        $topDir = trim(trim($topDir), "\\/");
        if (empty($topDir)) {
            $topDir = '';
        } else {
            $topDir = $topDir . "/";
        }

        if (empty($url)) {
            return "{$urlPath}{$pluginsDir}{$topDir}";
        }

        $isString = false;
        if (is_string($url)) {
            $urls = [
                $topDir => $url
            ];
            $isString = true;
        } elseif (is_array($url)) {
            $urls = $url;
        } else {
            return '';
        }

        $ret = [];
        $isRun = in_array($type, ['css', 'js']);
        foreach ($urls as $baseDir => $u) {
            list($baseFlag, $baseDir) = self::getStaticFlag($baseDir);
            $importDir = $baseDir;
            if (is_string($baseDir)) {
                $baseDir = trim(trim(str_replace(".", "/", $baseDir)), "\\/");
                if (empty($baseDir)) {
                    $baseDir = '/';
                } else {
                    $baseDir = "{$pluginsDir}{$baseDir}/";
                }
            } else {
                $baseDir = $pluginsDir . $topDir;
            }

            if (is_string($u)) {
                $u = [$u];
            } elseif (!is_array($u)) {
                continue;
            }

            foreach ($u as $_u) {
                if (is_string($_u)) {
                    $_u = trim($_u);
                    if ($_u[0] == ':' && $isRun) {
                        self::getStaticHandle($importDir, ltrim($_u, ":"), $type);
                        continue;
                    }
                    if ($type == 'static') {
                        $pos = strpos($_u, ":");
                        if ($pos !== false) {
                            $tType = strtolower(trim(substr($_u, 0, $pos)));
                            if (in_array($tType, ['css', 'js'])) {
                                $_u = trim(substr($_u, $pos + 1));
                                if (empty($_u)) {
                                    continue;
                                }
                                self::getStaticHandle($importDir, $_u, $tType);
                            }
                        }
                    }
                    $_u = trim($_u, '\\/');
                    list($_flag, $_u) = self::getStaticFlag($_u, $baseFlag);
                    if (TplHandle::isUrl($_u)) {
                        $ret[] = "{$_flag}{$_u}";
                    } else {
                        $ret[] = "{$_flag}{$urlPath}{$baseDir}{$_u}";
                        self::getStaticHandle($importDir, $_u);
                    }
                }
            }
        }
        $ret = array_unique($ret);
        if ($isString) {
            return $ret[0];
        }
        return $ret;
    }

    /**
     * 获取插件文件路径 plugins static 动态加载CSS文件
     * @param null $url 指定路径
     * @param bool $isDomain 是否显示域名或IP地址
     * @return array|string
     */
    public static function getStaticCSS($url = null, $isDomain = true)
    {
        $urls = self::getStatic($url, $isDomain, 'css');
        if (empty($urls)) {
            return;
        }
        if (is_string($urls)) {
            $urls = [$urls];
        }
        foreach ($urls as $u) {
            if (!in_array($u, TplInit::$css)) {
                TplInit::$css[] = $u;
            }
        }
    }

    /**
     * 获取插件文件路径 plugins static 动态加载JS文件
     * @param null $url 指定路径
     * @param bool $isDomain 是否显示域名或IP地址
     * @return array|string
     */
    public static function getStaticJS($url = null, $isDomain = true)
    {
        $urls = self::getStatic($url, $isDomain, 'js');
        if (empty($urls)) {
            return;
        }
        if (is_string($urls)) {
            $urls = [$urls];
        }
        foreach ($urls as $u) {
            if (!in_array($u, TplInit::$js)) {
                TplInit::$js[] = $u;
            }
        }
    }

    /**
     * @return string
     */
    private static function getRunJsId($md5)
    {
        if (!isset(TphpConfig::$obStart['imports'])) {
            TphpConfig::$obStart['imports'] = [];
        }

        $imports = &TphpConfig::$obStart['imports'];
        if (!isset($imports['runjs_inc'])) {
            $imports['runjs_inc'] = 0;
        }
        $imports['runjs_inc'] ++;

        return "{$md5}_{$imports['runjs_inc']}";
    }

    /**
     * 动态获取运行JS实例
     * @param $pluDir
     * @param $dir
     * @param $fun
     * @param $md5
     * @param $args
     * @param $runJsId
     * @return string
     */
    public static function getRunJS($pluDir, $dir, $fun, $md5, $args, $runJsId)
    {
        if (TplHandle::isUrl($dir)) {
            return $runJsId;
        }

        list($isOnly, $path, $pathDefault) = self::getPath($dir, $pluDir);
        if (!empty($pathDefault)) {
            $pluDir = $pathDefault;
        }

        list($browser) = Run::getBrowser();

        $pluPath = self::getPluginDir($pluDir);
        $jsFile = $pluPath . "/{$path}/view.js";
        $jsBrowserFile = $pluPath . "/{$path}/view.{$browser}.js";

        $isFile = false;
        if (is_file($jsFile) || is_file($jsBrowserFile)) {
            $isFile = true;
        } elseif (empty($fun)) {
            return $runJsId;
        }
        if (empty($runJsId)) {
            $initStr = '';
            $runJsId = self::getRunJsId($md5);
        } else {
            $initStr = '#';
        }
        $imports = &TphpConfig::$obStart['imports'];

        if (!isset($imports['runJsIds'])) {
            $imports['runJsIds'] = [];
        }
        $runJsIds = &$imports['runJsIds'];

        if ($isFile) {
            if (!isset($imports['runjs'])) {
                $imports['runjs'] = [];
            }
            $runJs = &$imports['runjs'];
            if (!isset($runJs[$pluDir])) {
                $runJs[$pluDir] = [];
            }
            $runJs[$pluDir][$path] = false;
            $runJsIds[$initStr . $runJsId] = [$fun, $args, $pluDir, $path];
        } else {
            $runJsIds[$initStr . $runJsId] = [$fun, $args];
        }
        return $runJsId;
    }

    /**
     * 获取路径转化
     * @param $path
     * @param string $pluDir
     * @return array
     */
    private static function getPath($path, $pluDir = '')
    {
        $isOnly = false;
        $path = str_replace('/', '.', $path);
        $path = strtolower(str_replace('\\', '.', $path));
        $pathR = "";
        $pos = strpos($path, "==");
        if ($pos !== false) {
            $isOnly = true;
            $pathR = substr($path, $pos + 2);
            $path = substr($path, 0, $pos);
        }
        $pos = strpos($path, "=");
        if ($pos !== false) {
            $pathR = substr($path, $pos + 1);
            $path = substr($path, 0, $pos);
        }
        $path = trim(trim($path), ".");
        $pathR = trim(trim($pathR), ".");
        $pathR = ltrim($pathR, "=");
        $pathR = ltrim(trim($pathR), ".");
        $paths = explode(".", $path);
        $pathRs = explode(".", $pathR);
        if (count($pathRs) == 1) {
            $pr0 = trim($pathRs[0]);
            if (!empty($pr0)) {
                $pd_arr = explode("/", $pluDir);
                if (!empty($pd_arr[0])) {
                    $pathRs = [
                        $pd_arr[0],
                        $pr0
                    ];
                }
            }
        }
        return [$isOnly, implode("/", $paths), implode("/", $pathRs)];
    }

    /**
     * 获取全局插件 call 、 view 、 model 和 config 目录
     * @return array|mixed
     */
    public static function getPluginPaths()
    {
        $pluginPaths = TphpConfig::$plugins;
        if (is_array($pluginPaths)) {
            return $pluginPaths;
        }
        $pluginPaths = [
            'base' => [],
            'cache' => [],
            'ref' => [],
            'class' => [],
            'plu' => [],
            'load' => []
        ];
        $pluginLoop = [
            'base'
        ];

        $pluginBasePath = Register::getHtmlPath(true) . "/plugins/";
        $pluginSysPaths = [];
        if (is_dir($pluginBasePath)) {
            $pluginSysPaths[] = $pluginBasePath;
        }

        foreach (Register::$viewPaths as $vp) {
            $vp .= "/html/plugins/";
            if (is_dir($vp)) {
                $pluginSysPaths[] = $vp;
            }
        }

        $xFile = import('XFile');
        $tops = [];
        foreach ($pluginSysPaths as $psp) {
            foreach ($xFile->getDirs($psp) as $top) {
                $tops[] = $top;
            }
        }
        if (empty($tops)) {
            return $pluginPaths;
        }
        $tops = array_unique($tops);
        sort($tops);
        foreach ($tops as $top) {
            $dirs = [];
            foreach ($pluginSysPaths as $psp) {
                foreach ($xFile->getDirs("{$psp}{$top}") as $gDir) {
                    $dirs[] = $gDir;
                }
            }

            if (empty($dirs)) {
                continue;
            }
            $dirs = array_unique($dirs);
            sort($dirs);

            foreach ($dirs as $dir) {
                $key = "{$top}/{$dir}";
                foreach ($pluginLoop as $pl) {
                    foreach ($pluginSysPaths as $psp) {
                        $pDir = "{$psp}{$top}/{$dir}/{$pl}";
                        if (is_dir($pDir)) {
                            $pluginPaths[$pl][] = $key;
                            break;
                        }
                    }
                }
            }
        }
        TphpConfig::$plugins = $pluginPaths;
        return $pluginPaths;
    }

    /**
     * 获取合并路径
     * @param $isOnly 是否仅默认路径
     * @param $paths 当前所有路径
     * @param $pathDefault 默认路径
     * @return array|null
     */
    private static function getMergePath($isOnly, $paths, $pathDefault)
    {
        if ($isOnly) {
            if (!in_array($pathDefault, $paths)) {
                return null;
            }
            $retPaths = [$pathDefault];
        } elseif (in_array($pathDefault, $paths)) {
            $retPaths = [$pathDefault];
            foreach ($paths as $cp) {
                if ($pathDefault === $cp) {
                    continue;
                }
                $retPaths[] = $cp;
            }
        } else {
            $retPaths = $paths;
        }
        return $retPaths;
    }

    /**
     * 获取 ReflectionClass
     * @param $fun
     * @param $md5
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    private static function getReflectionClass($fun, $md5)
    {
        $retFun = TphpConfig::$plugins['ref'][$md5];
        if (!empty($retFun)) {
            return $retFun;
        }
        $retFun = new \ReflectionClass($fun);
        TphpConfig::$plugins['ref'][$md5] = $retFun;
        return $retFun;
    }

    /**
     * 设置插件数据
     * @param $plu
     * @param array $data
     * @return PluginClass
     */
    public static function setPluginClassData($plu, $data = [])
    {
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $plu->$key = $val;
            }
        }
        return $plu;
    }

    /**
     * 设置类默认信息
     * @param $fun
     * @param $plu
     */
    private static function setPluginClass($fun, $plu)
    {
        $funObj = self::getReflectionClass($fun, $plu['md5']);
        $funKeys = array_keys($funObj->getDefaultProperties());
        if (in_array('plu', $funKeys)) {
            $gp = $funObj->getProperty('plu');
            if ($gp->isPublic()) {
                if ($gp->isStatic()) {
                    if (!is_object($fun::$plu)) {
                        $fun::$plu = new PluginClass();
                    }
                    self::setPluginClassData($fun::$plu, $plu);
                } else {
                    if (!is_object($fun->plu)) {
                        $fun->plu = new PluginClass();
                    }
                    self::setPluginClassData($fun->plu, $plu);
                }
            }
        }
    }

    /**
     * 判断调用是否存在
     * @param string $path
     * @param PluginClass $pluObj
     * @return bool
     * @throws \ReflectionException
     */
    public static function hasCall($path = '', PluginClass $pluObj = null) : bool
    {
        if (!is_string($path)) {
            return false;
        }

        $path = trim($path);
        $path = ltrim($path, " #");
        if (empty($path)) {
            return false;
        }

        return self::call([$path], $pluObj, false, true) === true;
    }

    /**
     * 获取调用
     * @param string $path
     * @param PluginClass $pluObj
     * @return bool
     * @throws \ReflectionException
     */
    public static function getCall($path = '', PluginClass $pluObj = null)
    {
        if (!is_string($path)) {
            return null;
        }

        $path = trim($path);
        $path = ltrim($path, " #");
        if (empty($path)) {
            return null;
        }

        $fun = self::call([$path], $pluObj, true);

        if (!is_function($fun)) {
            return null;
        }

        return $fun;
    }

    /**
     * 包含文件
     * @param string $file
     * @return mixed
     */
    private static function requireFile($file = '')
    {
        return require $file;
    }

    /**
     * 执行插件函数
     * @param array $args
     * @param PluginClass $pluObj
     * @param bool $returnMethod
     * @param bool $isGetCall
     * @param bool $isNew
     * @return null
     * @throws \ReflectionException
     */
    public static function call($args = [], PluginClass $pluObj = null, $returnMethod = false, $isGetCall = false, $isNew = false)
    {
        $argsNum = count($args);
        if ($argsNum <= 0) return null;
        $arg0 = $args[0];
        $initArgs = [];
        if (is_array($arg0)) {
            if (empty($arg0)) {
                return null;
            }
            $initArgs = array_values($arg0);
            $arg0 = $initArgs[0];
            unset($initArgs[0]);
            $initArgs = array_values($initArgs);
        }
        if (empty($arg0) || !is_string($arg0)) {
            return null;
        } else {
            $arg0 = trim($arg0);
            if (empty($arg0)) {
                return null;
            }
        }

        // 是否仅打印调用路径
        $showPath = false;
        if ($arg0[0] === '#') {
            $showPath = true;
            $arg0 = ltrim($arg0, "#");
            if (empty($arg0)) {
                return null;
            }
        }

        // 获取类中的方法
        $pos = strpos($arg0, ":");
        $method = "";
        if ($pos !== false) {
            $pathL = substr($arg0, 0, $pos);
            $pathR = substr($arg0, $pos + 1);
            $pos = strpos($pathR, "=");
            if ($pos === false) {
                $arg0 = $pathL;
                $method = $pathR;
            } else {
                $arg0 = $pathL . substr($pathR, $pos);
                $method = substr($pathR, 0, $pos);
            }
            $method = trim(str_replace(":", "", $method));
        }
        if (empty($arg0)) {
            return null;
        }
        unset($args[0]);
        $args = array_values($args);
        $callData = self::getBasePath($arg0, $pluObj->getDir(), 'call');
        if (empty($callData)) {
            return null;
        }

        list($pluginDir, $filePath, $srcPath, $cachePath) = $callData;
        if ($showPath) {
            return "<pre>{$pluginDir}</pre>";
        }

        if ($isNew) {
            $pdMd5 = '#';
            $fun = null;
        } else {
            $pdMd5 = substr(md5($cachePath), 8, 16);
            $fun = TphpConfig::$plugins['cache'][$pdMd5];
        }
        
        if (!isset($fun)) {
            if (empty($filePath)) {
                !$isNew && TphpConfig::$plugins['cache'][$pdMd5] = false;
                return null;
            }
            $fun = self::requireFile($filePath);
            if (!empty($initArgs) && is_object($fun) && method_exists($fun, '__init')) {
                $funInfo = new \ReflectionMethod($fun, '__init');
                if ($funInfo->isPublic()) {
                    if ($funInfo->isStatic()) {
                        $fun::__init(...$initArgs);
                    } else {
                        $fun->__init(...$initArgs);
                    }
                }
            }
            if (empty($fun)) {
                $fun = false;
            }
            // 保存到全局变量中，保证匿名函数或匿名类一次创建，多次调用
            if (!$isNew) {
                TphpConfig::$plugins['cache'][$pdMd5] = $fun;
            }
        }

        $tpl = $pluObj->tpl;
        // 如果是方法，直接调用
        if (is_function($fun)) {
            if (empty($method)) {
                if ($isGetCall) {
                    return true;
                }

                $args[] = $pluObj;
                if ($returnMethod) {
                    return $fun;
                }
                return $fun(...$args);
            }
            return null;
        }

        if (is_object($fun)) {
            if (empty($method)) {
                $method = 'index';
            }
            // 如果匿名类中找到index方法则调用index方法
            if (method_exists($fun, $method)) {
                if ($isGetCall) {
                    return true;
                }
                self::setPluginClass($fun, [
                    'dir' => $pluginDir,
                    'md5' => $pdMd5,
                    'path' => $srcPath,
                    'tpl' => $tpl
                ]);
                if ($returnMethod) {
                    $isNew = $args[0];
                    if (!is_bool($isNew)) {
                        $isNew = false;
                    }
                    return TplInit::getClassMethod($fun, $method, $isNew);
                }

                $funInfo = new \ReflectionMethod($fun, $method);
                $isReturn = false;
                if ($funInfo->isPublic()) {
                    $isReturn = true;
                    if ($funInfo->isStatic()) {
                        $funReturn = $fun::{$method}(...$args);
                    } else {
                        $funReturn = $fun->{$method}(...$args);
                    }
                }

                if (method_exists($fun, '__last')) {
                    $lastInfo = new \ReflectionMethod($fun, '__last');
                    if ($lastInfo->isPublic()) {
                        if ($lastInfo->isStatic()) {
                            $fun::__last();
                        } else {
                            $fun->__last();
                        }
                    }
                }

                if ($isReturn) {
                    return $funReturn;
                }

                return null;
            }
        }

        return null;
    }

    /**
     * 获取 call 目录对象
     * @param string $path
     * @param null $tpl
     * @param string $pluDir
     * @return bool|mixed|null
     */
    public static function caller($path = '', $tpl = null, $pluDir = '')
    {
        if (!is_string($path)) {
            return null;
        }
        $path = trim($path);
        if (empty($path)) {
            return null;
        }
        $showPath = false;
        if ($path[0] === "#") {
            $showPath = true;
            $path = ltrim($path, "#");
            if (empty($path)) {
                return null;
            }
        }
        if ($path[0] === '=') {
            $path = "index{$path}";
        }

        $configData = self::getBasePath($path, $pluDir, 'call');
        if (empty($configData)) {
            if ($showPath) {
                echo self::getPathErrorString($path, 'Caller');
            }
            return null;
        }

        list($pluginDir, $filePath, $srcPath, $cachePath) = $configData;
        if ($showPath) {
            echo "<pre>{$pluginDir}</pre>";
        }

        $pdMd5 = substr(md5($cachePath), 8, 16);
        $config = TphpConfig::$plugins['cache'][$pdMd5];
        if (!isset($config)) {
            $config = TplInit::includeFile($filePath);
            if (!isset($config)) {
                $config = false;
            }
            TphpConfig::$plugins['cache'][$pdMd5] = $config;
        }

        if (is_object($config)) {
            self::setPluginClass($config, [
                'dir' => $pluginDir,
                'md5' => $pdMd5,
                'path' => $srcPath,
                'tpl' => $tpl
            ]);
        }
        return $config;
    }

    /**
     * 获取中心路径
     * @param $path 插件相对路径
     * @param $pluDir
     * @param $type
     * @return array|null
     */
    private static function getBasePath($path, $pluDir, String $type = '')
    {
        if (empty($type)) {
            return null;
        }

        $basePaths = self::getPluginPaths()['base'];
        if (empty($basePaths)) {
            return null;
        }
        list($isOnly, $path, $pathDefault) = self::getPath($path, $pluDir);
        if (!empty($pathDefault)) {
            $basePaths = self::getMergePath($isOnly, $basePaths, $pathDefault);
            if (empty($basePaths)) {
                return null;
            }
        }

        if ($type == 'view') {
            $typeFileName = "{$type}.blade";
        } else {
            $typeFileName = $type;
        }

        $pluginDir = null;
        $filePath = null;
        $rootPath = null;
        foreach ($basePaths as $bp) {
            $pPath = self::getPluginDir($bp);
            $bpPhpFile = "{$pPath}/{$path}/{$typeFileName}.php";
            if (is_file($bpPhpFile)) {
                $filePath = $bpPhpFile;
                $pluginDir = $bp;
                $rootPath = "{$pPath}/{$path}";
                $cachePath = str_replace("\\", ".", "{$bp}.base.{$path}:{$type}");
                $cachePath = "plugins." . str_replace("/", ".", $cachePath);
                break;
            }
        }

        if (empty($filePath)) {
            return null;
        }

        if (TphpConfig::$plugins['load'][$pluginDir] !== true) {
            PluginClass::__init($pluginDir);
        }

        return [$pluginDir, $filePath, $path, $cachePath, $rootPath];
    }

    /**
     * 用插件函数 plugins view 文件夹
     * @param string $path 视图路径
     * @param null $data 视图传递数据
     * @param bool $errPrint 默认返回信息， 如果是字符串则直接返回， 如果为true则打印路径和数据
     * @param null $tpl 模板
     * @param bool $returnPath 是否返回路径
     * @param string $pluDir
     * @return bool|string
     */
    public static function view($path = '', $data = null, $errPrint = true, $tpl = null, $returnPath = false, $pluDir = '')
    {
        if (empty($path)) {
            return '';
        }
        $showPath = false;
        if ($path[0] === '#') {
            $showPath = true;
            $path = ltrim($path, "#");
            if (empty($path)) {
                return '';
            }
        }
        $viewData = self::getBasePath($path, $pluDir, 'view');
        if (empty($viewData)) {
            if ($returnPath) {
                return '';
            }
            if ($showPath) {
                return self::getPathErrorString($path, 'View');
            } elseif ($errPrint === true) {
                $errorString = self::getPathErrorString($path, 'View');
                $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                return "{$errorString}<pre>{$json}</pre>";
            } elseif (is_string($errPrint)) {
                return $errPrint;
            }
            return '';
        }
        list($pluginDir, $filePath, $srcPath, $cachePath, $rootPath) = $viewData;

        $viewRealPath = str_replace(":", ".", $cachePath);
        if ($returnPath) {
            return $viewRealPath;
        }
        if ($showPath) {
            return "<pre>{$viewRealPath}</pre>";
        }
        if (empty($data)) {
            $data = [];
        } elseif (!is_array($data)) {
            $data = [
                '_DATA_' => $data
            ];
        }
        $pluMd5 = substr(md5($cachePath), 8, 16);
        $plu = TphpConfig::$plugins['class'][$pluMd5];
        if (!is_object($plu)) {
            $plu = new PluginClass();
            TphpConfig::$plugins['class'][$pluMd5] = $plu;
        }
        $data['plu'] = $plu;
        $data['tpl'] = $tpl;
        self::setPluginClassData($data['plu'], [
            'dir' => $pluginDir,
            'md5' => $pluMd5,
            'path' => $srcPath,
            'tpl' => $tpl
        ]);

        if (view()->exists($viewRealPath)) {
            // 加载相同路径的scss或js文件
            
            list($browser) = Run::getBrowser();
            
            foreach (['scss', 'js'] as $typeName) {
                if (is_file($rootPath . "/view.{$typeName}") || is_file($rootPath . "/view.{$browser}.{$typeName}")) {
                    TplHandle::setPluginsStaticPaths($pluginDir, $srcPath, $typeName, true);
                }
            }
            return view($viewRealPath, $data)->toHtml();
        } elseif (is_string($errPrint)) {
            return $errPrint;
        }

        return '';
    }

    /**
     * 返回空Model
     * @return PluginModel
     */
    private static function getEmptyModel()
    {
        $keyName = 'EmptyModel';
        $emptyModel = TphpConfig::$plugins['class'][$keyName];
        if (empty($emptyModel)) {
            $emptyModel = new PluginModel();
            $emptyModel->setEmptyModel(true);
            TphpConfig::$plugins['class'][$keyName] = $emptyModel;
        }
        return $emptyModel;
    }

    /**
     * 获取数组MD5组合
     * @param array $array
     * @return bool|string
     */
    private static function getArrayToMd5($array = [])
    {
        if (empty($array)) {
            return '';
        }
        $newArray = [];
        foreach ($array as $key => $val) {
            if (!isset($val) || $val == '') {
                continue;
            }
            $newArray[$key] = $val;
        }
        if (empty($newArray)) {
            return '';
        }
        ksort($newArray);
        return substr(md5(json_encode($newArray, true)), 8, 16);
    }

    /**
     * 获取数据库链接名称，对数组配置处理
     * @param string $conn
     * @return bool|string
     */
    public static function getConnectionName($conn = '')
    {
        if (is_function($conn)) {
            $conn = $conn();
        }

        if (empty($conn) || !is_array($conn)) {
            return $conn;
        }
        $connMd5 = self::getArrayToMd5($conn);
        config(["database.connections.{$connMd5}" => $conn]);
        $conn = $connMd5;
        return $conn;
    }

    /**
     * 获取连接名称
     * @param null $conn
     * @return array
     */
    private static function getModelForConnection($conn = '')
    {
        if (is_function($conn)) {
            $conn = $conn();
        }

        if (empty($conn)) {
            $tplConfig = tpl()->config['config'];
            if (!empty($tplConfig) && !empty($tplConfig['conn'])) {
                $conn = $tplConfig['conn'];
                if (is_function($conn)) {
                    $conn = $conn();
                }
                if (is_string($conn)) {
                    $conn = trim($conn);
                }
            }
            if (empty($conn)) {
                $conn = TphpConfig::$domain['conn'];
                if (empty($conn)) {
                    $conn = config('database.default');
                }
            }
        } else {
            $connections = config('database.connections');
            if (is_string($conn)) {
                $conn = trim($conn);
                if (empty($conn)) {
                    $conn = null;
                } elseif (!isset($connections[$conn])) {
                    return [false, self::getEmptyModel()];
                }
            } elseif (is_array($conn)) {
                $connMd5 = self::getArrayToMd5($conn);
                config(["database.connections.{$connMd5}" => $conn]);
                $conn = $connMd5;
            } else {
                return [false, self::getEmptyModel()];
            }
        }
        return [true, $conn];
    }

    /**
     * ORM 数据库功能
     * @param string $path model相对路径
     * @param string $conn 数据库链接， 如果为空则默认链接，如果为字符串则获取database.php文件获取，如果数组则新增链接
     * @param string $pluDir
     * @return array
     */
    private static function getModelForPath($path = "", $conn = null, $pluDir = '')
    {
        $showPath = false;
        if ($path[0] === '#') {
            $showPath = true;
            $path = ltrim($path, "#");
            if (empty($path)) {
                return [false, ''];
            }
        }
        $modelData = self::getBasePath($path, $pluDir, 'model');
        if (empty($modelData)) {
            if ($showPath) {
                return [false, self::getPathErrorString($path, 'Model')];
            }
            return [false, self::getEmptyModel()];
        }
        list($pluginDir, $filePath, $srcPath, $cachePath) = $modelData;
        if ($showPath) {
            return [false, "<pre>{$pluginDir}</pre>"];
        }

        list($status, $conn) = self::getModelForConnection($conn);
        if (!$status) {
            return [false, $conn];
        }

        $pdMd5 = substr(md5($cachePath), 8, 16);
        $modelFun = TphpConfig::$plugins['cache'][$pdMd5];
        if (!isset($modelFun)) {
            $modelFun = TplInit::includeFile($filePath);
            if (!isset($modelFun)) {
                $modelFun = false;
            }
            TphpConfig::$plugins['cache'][$pdMd5] = $modelFun;
        }

        return [true, [$modelFun, $conn]];
    }

    /**
     * ORM 数据库功能
     * @param string $array model配置
     * @param string $conn 数据库链接， 如果为空则默认链接，如果为字符串则获取database.php文件获取，如果数组则新增链接
     * @return PluginModel
     */
    private static function getModelForArray($array = "", $conn = null)
    {
        if (empty($array)) {
            return [false, self::getEmptyModel()];
        }

        list($status, $conn) = self::getModelForConnection($conn);
        if (!$status) {
            return [false, $conn];
        }

        return [true, [$array, $conn]];

    }

    /**
     * ORM 数据库功能
     * @param string $info model相对路径或模型
     * @param string $conn 数据库链接， 如果为空则默认链接，如果为字符串则获取database.php文件获取，如果数组则新增链接
     * @param PluginClass $pluObj 插件对象
     * @param array $reset 数组重设
     * @return PluginModel
     */
    public static function model($info = "", $conn = null, PluginClass $pluObj = null, $reset = [])
    {
        if (is_array($info)) {
            list($status, $data) = self::getModelForArray($info, $conn);
        } else {
            list($status, $data) = self::getModelForPath($info, $conn, $pluObj->getDir());
        }

        if (!$status) {
            return $data;
        }

        list($modelFun, $conn) = $data;

        // 如果是方法，则调用
        if (is_function($modelFun)) {
            $modelFun = $modelFun();
        }

        if (!is_array($modelFun)) {
            return self::getEmptyModel();
        }

        $resetInner = $reset['reset'];
        if (isset($resetInner)) {
            unset($reset['reset']);
            if (is_function($resetInner)) {
                $resetData = $resetInner($modelFun);
                if (!empty($resetData) && is_array($resetData)) {
                    $modelFun = $resetData;
                }
            }
        }

        if (!is_array($modelFun) || empty($modelFun['table']) || empty($modelFun['field'])) {
            return self::getEmptyModel();
        }

        if (isset($modelFun['connection'])) {
            unset($modelFun['connection']);
        }
        if (isset($modelFun['status'])) {
            unset($modelFun['status']);
        }
        $init = [];
        if (isset($modelFun['init'])) {
            $init = $modelFun['init'];
            unset($modelFun['init']);
        }
        if (!empty($conn)) {
            $modelFun['connection'] = $conn;
        }

        $envInit = [
            // 创建时间字段设定
            'CREATED_AT' => 'createdAt',
            // 创建时间字段设定（字段备注）
            'CREATED_AT_COMMENT' => 'createdAtComment',
            // 修改时间字段设定
            'UPDATED_AT' => 'updatedAt',
            // 修改时间字段设定（字段备注）
            'UPDATED_AT_COMMENT' => 'updatedAtComment',
            // 时间字段类型设定
            'DATE_FORMAT' => 'dateFormat',
        ];

        foreach ($envInit as $envKey => $modelKey) {
            if (empty($modelFun[$modelKey])) {
                $envValue = env($envKey);
                if (!empty($envValue)) {
                    $modelFun[$modelKey] = $envValue;
                }
            }
        }

        $pluModel = new PluginModel([], $modelFun, $pluObj, $reset);
        if ($pluModel->isInitCreate) {
            if (is_function($init)) {
                $init = $init();
            }
            if (empty($init) || !is_array($init)) {
                return $pluModel;
            }

            try {
                // 强制创建时间和更新时间
                if ($pluModel->timestamps) {
                    $createdAt = $pluModel->getCreatedAtColumn();
                    $updatedAt = $pluModel->getUpdatedAtColumn();
                    if (!is_array($init[0])) {
                        $init = [$init];
                    }
                    $now = date($pluModel->getDateFormat());
                    foreach ($init as $key => $val) {
                        if (is_array($val)) {
                            $init[$key][$createdAt] = $now;
                            $init[$key][$updatedAt] = $now;
                        }
                    }
                }
                $pluModel->insert($init);
            } catch (\Exception $e) {
                echo "<div>初始化数据失败，刷新即可</div>";
                echo "<div>" . $e->getMessage() . "</div>";
                __exit();
            }
        }
        return $pluModel;
    }

    /**
     * config 功能配置，与Laravel功能类似
     * @param null $config
     * @param null $default
     * @param PluginClass|null $pluObj
     * @return mixed
     */
    public static function config($config = null, $default = null, PluginClass $pluObj = null)
    {
        if (empty($pluObj)) {
            return null;
        }
        
        if (is_null($config)) {
            return $pluObj::$configs[$pluObj->getDir()];
        }

        if (is_array($config)) {
            foreach ($config as $keyName => $data) {
                if (!is_string($keyName)) {
                    continue;
                }
                $pluObj->autoLoadConfigSet($keyName, $data);
            }
            return null;
        } elseif (!is_string($config)) {
            return null;
        }

        if (empty($config)) {
            return $default;
        }

        $keyName = str_replace("/", ".", $config);
        $keyName = str_replace("\\", ".", $keyName);

        $pluDir = $pluObj->getDir();
        list($isOnly, $keyName, $pathDefault) = self::getPath($keyName, $pluDir);
        if ($isOnly) {
            if (!empty($pathDefault)) {
                $pds = explode("/", $pathDefault);
                if (count($pds) == 2) {
                    $pluDir = $pathDefault;
                    if (TphpConfig::$plugins['load'][$pluDir] !== true) {
                        PluginClass::__init($pluDir);
                    }
                }
            }
        }

        $config = $pluObj::$configs[$pluDir];

        if (empty($keyName)) {
            return $config;
        }

        $kArr = explode("/", $keyName);
        foreach ($kArr as $k) {
            if (empty($config) || !is_array($config)) {
                $config = null;
                break;
            }
            
            $config = $config[$k];
        }

        return $config ?? $default;
    }
}
