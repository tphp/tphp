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

use PhpParser\ParserFactory;
use Tphp\Basic\Tpl\Init as TplInit;
use Tphp\Basic\Tpl\Run as TplRun;
use Tphp\Register;
use Tphp\Config as TphpConfig;

class PluginClass
{
    /**
     * @var 插件当前路径
     */
    public $dir;

    /**
     * @var 调用路径键值
     */
    public $md5;

    /**
     * @var 模板
     */
    public $tpl;

    /**
     * @var string 静态页面路径
     */
    public $staticUrl = "/static/plugins/";

    /**
     * @var null 是否强制指定目录
     */
    private $isOnly = null;

    /**
     * 是否自动加载文件
     * @var bool
     */
    private $isLoad = false;

    private $phpParser = null;

    /**
     * 加载路径标识
     * @var array
     */
    private static $loads = [];

    /**
     * config 文件配置
     * @var array
     */
    public static $configs = [];

    public function getDir()
    {
        list($status, $dir, $isOnly) = $this->getDirInfo();
        if (!$status || empty($dir)) {
            return $dir;
        }
        $dir = trim($dir, '@');
        return str_replace(".", "/", $dir);
    }

    /**
     * 获取默认目录
     * @return array
     */
    private function getDirInfo()
    {
        $isOnly = $this->isOnly;
        $dir = $this->dir;
        if (empty($dir)) {
            $tpl = $this->tpl;
            if (!empty($tpl) && is_array($tpl->config)) {
                $tplConfig = $tpl->config;
            } elseif(!empty($tpl->dataPath)) {
                $tplConfig = TphpConfig::$dataFileInfo[$tpl->dataPath . "data.php"];
            }
            if (is_array($tplConfig) && is_array($tplConfig['plu'])) {
                $tplPlu = $tplConfig['plu'];
                if (is_array($tplPlu)) {
                    $tplPluDir = $tplPlu['dir'];
                    if ($tplPluDir === false) {
                        return [false, $dir, $isOnly];
                    }

                    if (is_string($tplPluDir)) {
                        $dir = trim($tplPluDir);
                    }
                }
            }
            if (empty($dir)) {
                $dc = TphpConfig::$domain;
                if (is_array($dc['plu'])) {
                    $dcPluDir = $dc['plu']['dir'];
                    if ($dcPluDir === false) {
                        return [false, $dir, $isOnly];
                    }
                    if (is_string($dcPluDir)) {
                        $dir = trim($dcPluDir);
                    }
                }
            }
            if (!empty($dir)) {
                $isOnly = true;
            }
        }
        return [true, $dir, $isOnly];
    }

    /**
     * 获取入口路径文件
     * @return String
     */
    public function runMain()
    {
        $thisDir = $this->getDir();

        $path = Register::getViewPath("plugins/{$thisDir}/main.php", false);
        if (is_file($path) && !class_exists("\MainController")) {
            include_once $path;
        }
    }

    /**
     * 获取自动路径
     * @param $path 插件路径
     * @param string $default 指定目录默认值
     * @return string
     */
    private function getAutoPath($path, $default = 'index')
    {
        if (strpos($path, '=') !== false) {
            return $path;
        }

        $isOnly = $this->isOnly;
        list($status, $dir, $isOnly) = $this->getDirInfo();
        if (!$status || empty($dir)) {
            return $path;
        }
        empty($path) && $path = $default;
        if (empty($path)) {
            return $path;
        }

        if (!isset($isOnly) || $isOnly === true) {
            $path .= '==' . $dir;
        } elseif ($isOnly === false) {
            $path .= '=' . $dir;
        }

        return $path;
    }

    /**
     * 读取PHP文件的类路径
     * @param string $phpCode
     * @return array
     */
    private function getPhpClass($phpCode = '')
    {
        if (empty($this->phpParser)) {
            $this->phpParser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        }

        $retList = [];
        try {
            $parse = $this->phpParser->parse($phpCode);
            foreach ($parse as $ps) {
                if ($ps instanceof \PhpParser\Node\Stmt\Namespace_) {
                    $namespace = implode("\\", $ps->name->parts);
                    if (is_array($ps->stmts)) {
                        foreach ($ps->stmts as $st) {
                            if (is_object($st) && is_object($st->name) && isset($st->name->name)) {
                                $retList["{$namespace}\\{$st->name->name}"] = true;
                            }
                        }
                    }
                } elseif (is_object($ps) && is_object($ps->name) && isset($ps->name->name)) {
                    $retList[$ps->name->name] = true;
                }
            }
        } catch (\Exception $e) {
            // Nothing Todo
        }
        return $retList;
    }

    /**
     * 自动加载 src 文件夹中的所有文件， 自动命名空间
     * @param $xFile
     */
    private function autoLoadSrc($xFile)
    {
        $basePaths = $this->getBasePaths('src');

        if (empty($basePaths)) {
            return;
        }
        
        $fileList = [];
        $autoload = [];
        foreach ($basePaths as $basePath) {
            $files = $xFile->getAllFiles($basePath);

            if (empty($files)) {
                continue;
            }

            foreach ($files as $file) {
                if ($file == 'autoload') {
                    $atContent = trim($xFile->read("{$basePath}/{$file}"));
                    if (empty($atContent)) {
                        continue;
                    }

                    $atSplit = explode("\n", str_replace("\\", "/", $atContent));
                    foreach ($atSplit as $ats) {
                        $ats = trim($ats, " /");
                        $pos = strrpos($ats, ".");
                        if ($pos === false || strtolower(substr($ats, $pos + 1)) !== 'php') {
                            continue;
                        }
                        $atsFile = "{$basePath}/{$ats}";
                        if (is_file($atsFile)) {
                            $autoload[$atsFile] = true;
                        }
                    }
                    continue;
                }

                $pos = strrpos($file, ".");
                if ($pos === false) {
                    continue;
                }

                $ext = strtolower(substr($file, $pos + 1));

                if ($ext !== 'php') {
                    continue;
                }

                $filePath = "{$basePath}/{$file}";
                $pos = strrpos($file, "/");
                if ($pos === false) {
                    $fileExt = $file;
                } else {
                    $fileExt = substr($file, $pos + 1);
                }
                $pos = strrpos($fileExt, ".");
                if ($pos !== false) {
                    $fileExt = substr($fileExt, 0, $pos);
                }

                if (!isset($fileList[$fileExt])) {
                    $fileList[$fileExt] = [];
                }
                $fileList[$fileExt][$filePath] = [
                    'read' => false,
                    'load' => false,
                    'class' => []
                ];
            }
        }

        foreach ($autoload as $al => $bool) {
            require_once $al;
        }

        if (empty($fileList)) {
            return;
        }

        // autoload 文件自加载
        $classList = [];
        spl_autoload_register(function ($class) use (&$classList, &$fileList, $xFile) {
            if ($classList[$class]) {
                return;
            }

            $classList[$class] = true;

            $pos = strrpos($class, "\\");
            if ($pos === false) {
                $classExt = $class;
            } else {
                $classExt = substr($class, $pos + 1);
            }

            if (empty($fileList[$classExt])) {
                return;
            }

            $loadFile = '';
            foreach ($fileList[$classExt] as $fp => $fv) {
                if ($fv['load']) {
                    continue;
                }

                if (!$fv['read']) {
                    $cnt = $xFile->read($fp);
                    $fileList[$classExt][$fp]['read'] = true;
                    $fileList[$classExt][$fp]['class'] = $this->getPhpClass($cnt);
                }

                $cls = $fileList[$classExt][$fp]['class'];
                if (empty($cls) || empty($cls[$class])) {
                    continue;
                }

                $fileList[$classExt][$fp]['load'] = true;
                $loadFile = $fp;
                break;
            }

            if (!empty($loadFile)) {
                require_once $loadFile;
            }
        });
    }

    /**
     * 设置数据
     * @param $keyName
     * @param $data
     */
    public function autoLoadConfigSet($keyName, $data, $pluDir = null)
    {
        if (empty($pluDir)) {
            $pluDir = $this->getDir();
        }
        
        if (!isset(self::$configs[$pluDir])) {
            self::$configs[$pluDir] = [];
        }

        $keyName = str_replace("/", ".", $keyName);
        $keyName = str_replace("\\", ".", $keyName);
        $kArr = explode(".", $keyName);

        $config = &self::$configs[$pluDir];
        if (is_null($data)) {
            $isBreak = false;
            foreach ($kArr as $k) {
                if (!is_array($config) || !isset($config[$k])) {
                    $isBreak = true;
                    break;
                }
                $config = &$config[$k];
            }

            if (!$isBreak) {
                unset($config);
            }
            return;
        }

        $prevConfig = [];
        $prevKv = [];
        foreach ($kArr as $k) {
            if (!is_array($config[$k])) {
                $config[$k] = [];
            }
            $prevConfig = &$config;
            $prevKv = $k;
            $config = &$config[$k];
        }

        $prevConfig[$prevKv] = $data;
    }

    /**
     * 自动加载 config 文件夹中的所有文件， 配置文件
     * @param $xFile
     * @param $pluDir
     */
    private function autoLoadConfig($xFile, $pluDir)
    {
        $basePaths = $this->getBasePaths('config');

        if (empty($basePaths)) {
            return;
        }

        foreach ($basePaths as $basePath) {
            $files = $xFile->getAllFiles($basePath);

            if (empty($files)) {
                continue;
            }

            foreach ($files as $file) {
                $pos = strrpos($file, ".");
                if ($pos === false) {
                    continue;
                }

                $fileLower = strtolower($file);
                $ext = substr($fileLower, $pos + 1);

                if ($ext !== 'php') {
                    continue;
                }

                $keyName = substr($fileLower, 0, $pos);
                if (empty($keyName)) {
                    continue;
                }

                $data = include "{$basePath}/{$file}";
                if (empty($data) || !is_array($data)) {
                    continue;
                }

                $this->autoLoadConfigSet($keyName, $data, $pluDir);
            }
        }
    }

    /**
     * 执行自动加载
     * @param $autoload
     */
    protected function autoLoadRunning($autoload)
    {
        if (empty($autoload) || !is_array($autoload)) {
            return;
        }

        $isRuns = [];

        foreach ($autoload as $al) {
            if (is_function($al)) {
                $al($this);
                continue;
            }

            if (!is_string($al)) {
                continue;
            }

            if (class_exists($al) && !isset($isRuns[$al])) {
                $isRuns[$al] = true;
                new $al($this);
            }
        }
    }

    /**
     * 自动加载 src 和 config 文件夹中的所有文件
     */
    protected function autoLoad()
    {
        if ($this->isLoad) {
            return;
        }
        $this->isLoad = true;

        $thisDir = $this->getDir();

        if (empty($thisDir) || self::$loads[$thisDir] === true) {
            return;
        }

        self::$loads[$thisDir] = true;

        $xFile = import('XFile');

        $this->autoLoadSrc($xFile);
        $this->autoLoadConfig($xFile, $thisDir);

        $autoload = $this->config('autoload');
        if (!empty($autoload)) {
            $this->autoLoadRunning($autoload);
        }
    }

    /**
     * 初始化插件
     * @param string $dir
     * @param null $tpl 模板
     * @param bool $isOnly 是否强制指定目录
     * @return PluginClass
     */
    public static function __init($dir = '', $tpl = null, $isOnly = true) : PluginClass
    {
        if (!empty($dir)) {
            $dir = trim(str_replace("\\", "/", str_replace(".", "/", $dir)), " /");
            if (!empty($dir)) {
                $dirArr = explode("/", $dir);
                $dirArrLen = count($dirArr);
                if ($dirArrLen > 2) {
                    list($dirTop, $dirSub) = $dirArr;
                    $dirTop = trim($dirTop);
                    $dirSub = trim($dirSub);
                    if (empty($dirTop) || empty($dirSub)) {
                        $dir = "";
                    } else {
                        $dir = "{$dirTop}/{$dirSub}";
                    }
                } elseif ($dirArrLen < 2) {
                    $dir = "";
                }
            }
        }
        Init::getPluginPaths();
        $pluMd5 = substr(md5($dir), 8, 16);
        $plu = TphpConfig::$plugins['plu'][$pluMd5];
        if (!is_object($plu)) {
            $plu = new static();
            TphpConfig::$plugins['plu'][$pluMd5] = $plu;
        }
        $pluLoad = TphpConfig::$plugins['load'][$dir];
        if (!isset($pluLoad)) {
            TphpConfig::$plugins['load'][$dir] = true;
        }
        empty($dir) && $isOnly = false;
        $plu->isOnly = $isOnly;
        $plu->dir = $dir;
        $plu->md5 = $pluMd5;
        if (empty($tpl)) {
            $tpl = \Tphp\Config::$tpl;
        }
        $plu->tpl = $tpl;
        $plu->autoLoad();
        return $plu;
    }

    /**
     * 设置模块
     * @param null $tpl
     * @return $this
     */
    public function setTpl($tpl = null)
    {
        $this->tpl = $tpl;
        return $this;
    }

    /**
     * 获取视图，默认强制为当前目录下视图
     * @param string $path 视图路径，默认 index
     * @param array $data 参数传递
     * @param bool $errPrint 默认返回信息， 如果是字符串则直接返回， 如果为true则打印路径和数据
     * @param bool $returnPath 是否返回路径
     * @return string
     */
    public function view($path = '', $data = [], $errPrint = true, $returnPath = false)
    {
        if (is_string($path)) {
            $path = $this->getAutoPath($path, '');
        }
        return Init::view($this->getAutoPath($path), $data, $errPrint, $this->tpl, $returnPath, $this->getDir());
    }

    /**
     * 获取页面字段传递
     */
    public function getView($keyName = '')
    {
        $obj = TphpConfig::$plugins['tpl'];
        if (!empty($obj)) {
            return $obj->setView($keyName);
        }
        $vData = TphpConfig::$plugins['viewData'];
        !is_array($vData) && $vData = [];
        if (empty($keyName)) {
            if (is_string($keyName)) {
                return $vData;
            }
            return null;
        } elseif (!is_string($keyName)) {
            return null;
        }
        return $vData[$keyName];
    }

    /**
     * 设置页面字段传递
     */
    public function setView(...$args)
    {
        $argsNum = count($args);
        if ($argsNum <= 0) return;
        $args = func_get_args();
        $obj = TphpConfig::$plugins['tpl'];
        if (!empty($obj)) {
            $obj->setView(...$args);
            return;
        }
        $args0 = $args[0];
        $vData = TphpConfig::$plugins['viewData'];
        !is_array($vData) && $vData = [];
        if ($argsNum == 1) {
            if (is_array($args0)) {
                foreach ($args0 as $key => $val) {
                    if (is_string($key) && !empty($val)) {
                        $vData[$key] = $val;
                    }
                }
            } else {
                return;
            }
        } else {
            $vData[$args0] = $args[1];
        }
        TphpConfig::$plugins['viewData'] = $vData;
    }

    /**
     * 判断方法是否可调用
     * @param string $path
     * @return bool
     */
    public function hasCall($path = '')
    {
        if (empty($path)) {
            $path = 'index';
        }
        $path = $this->getAutoPath($path);
        return Init::hascall($path, $this);
    }

    /**
     * 获取执行方法值，默认强制为当前目录下调用
     * @return null
     */
    public function call(...$args)
    {
        $path = $args[0];
        if (empty($path)) {
            $path = 'index';
        }
        $path = $this->getAutoPath($path);
        $args[0] = $path;
        return Init::call($args, $this);
    }

    /**
     * 获取执行方法值，默认强制为当前目录下调用， 重新 new 一个 class
     * @return null
     */
    public function callNew(...$args)
    {
        $path = $args[0];
        if (empty($path)) {
            $path = 'index';
        }
        $path = $this->getAutoPath($path);
        $args[0] = $path;
        return Init::call($args, $this, false, false, true);
    }

    /**
     * TPHP 功能配置
     * @param string $path
     * @return bool|mixed|null
     */
    public function caller($path = '')
    {
        if (is_string($path)) {
            $path = $this->getAutoPath($path, '');
        }
        return Init::caller($path, $this->tpl, $this->getDir());
    }

    /**
     * 获取执行方法值，默认强制为当前目录下调用
     * @return null
     */
    public function getCall($path = '')
    {
        if (empty($path)) {
            $path = 'index';
        }
        $path = $this->getAutoPath($path);
        return Init::getCall($path, $this);
    }

    /**
     * 设置自定义方法，插件模式
     * @param null $methodKeyName
     * @param string $path
     * @param bool $isNew
     * @return TplInit\Method
     */
    public function method($methodKeyName = null, $path = '', bool $isNew = false)
    {
        if (is_string($path)) {
            $mPath = $path;
            $isArray = false;
        } else {
            $mPath = $path[0];
            $isArray = true;
        }
        if (empty($mPath)) {
            $mPath = 'index';
        }
        $mPath = $this->getAutoPath($mPath);
        if ($isArray) {
            $path[0] = $mPath;
        } else {
            $path = $mPath;
        }
        return TplInit::method($methodKeyName, Init::call([$path, $isNew], $this, true));
    }

    /**
     * 获取方法
     * @param null $methodKeyName
     * @return \Closure
     */
    public function getMethod($methodKeyName = null)
    {
        return TplInit::getMethod($methodKeyName);
    }

    /**
     * 运行方法
     * @param null $methodKeyName
     */
    public function runMethod($methodKeyName = null, ...$args)
    {
        return TplInit::runMethodPointer($methodKeyName, ...$args);
    }

    /**
     * 运行方法， 指针方式传递
     * @param null $methodKeyName
     */
    public function runMethodPointer($methodKeyName = null, &...$args)
    {
        return TplInit::runMethodPointer($methodKeyName, ...$args);
    }

    /**
     * 获取插件文件路径 plugins static 文件夹
     * @param null $url 指定路径
     * @param bool $isDomain 是否显示域名或IP地址
     * @return array|string
     */
    public function static($url = '', $isDomain = true)
    {
        return Init::getStatic($url, $isDomain, 'static', $this->getDir());
    }

    /**
     * 获取静态文件URL路径
     * @param string $dir 当前静态页面路径
     * @param bool $isDomain 是否加域名或IP
     * @return string
     */
    public function getStaticUrl($dir = '', $isDomain = true)
    {
        $dir = ltrim(trim($dir), "\\/");
        $staticUrl = $this->staticUrl . $this->getDir() . "/" . $dir;
        $isDomain && $staticUrl = url($staticUrl);
        return $staticUrl;
    }

    /**
     * 获取根路径
     * @param string $dir
     * @param bool $isPointer
     * @return mixed|string
     */
    public function getBasePath($dir = "", $isPointer = false)
    {
        $thisDir = $this->getDir();
        if (empty($thisDir)) {
            return '';
        }
        $dir = str_replace('.', '/', $dir);
        $dir = str_replace('\\', '/', $dir);
        $dir = trim(trim($dir), "/");
        if (!empty($dir)) {
            $dir = "/{$dir}";
        }

        $path = "plugins/{$thisDir}{$dir}";

        if ($isPointer) {
            return str_replace('/', '.', $path);
        }
        return $path;
    }

    /**
     * 获取根路径列表
     * @param string $dir
     * @return array
     */
    public function getBasePaths($dir = "")
    {
        $ret = [];
        $thisDir = $this->getDir();
        if (empty($thisDir)) {
            return $ret;
        }
        $dir = str_replace('.', '/', $dir);
        $dir = str_replace('\\', '/', $dir);
        $dir = trim(trim($dir), "/");
        if (!empty($dir)) {
            $dir = "/{$dir}";
        }

        $path = "plugins/{$thisDir}{$dir}";
        foreach (Register::$viewPaths as $vp) {
            $fullPath = "{$vp}/html/{$path}";
            if (is_dir($fullPath)) {
                $ret[] = $fullPath;
            }
        }

        $fullPath = Register::getHtmlPath(true) . "/{$path}";
        if (is_dir($fullPath)) {
            $ret[] = $fullPath;
        }

        return $ret;
    }

    /**
     * 获取指定路径转化
     * @param null $dirs
     * @return array
     */
    private function getStaticDirs($dirs = null)
    {
        $retDirs = [];
        if (empty($dirs)) {
            return $retDirs;
        }
        if (is_string($dirs)) {
            $dirs = [$dirs];
        } elseif (!is_array($dirs)) {
            return $retDirs;
        }
        $thisDir = $this->getDir();
        foreach ($dirs as $key=>$val) {
            if (is_int($key) || empty(trim($key))) {
                $key = $thisDir;
            }
            empty($retDirs[$key]) && $retDirs[$key] = [];
            if (is_string($val)) {
                $retDirs[$key][] = $val;
            } elseif (is_array($val)) {
                foreach ($val as $v) {
                    if (is_string($v)) {
                        $retDirs[$key][] = $v;
                    }
                }
            }
        }
        return $retDirs;
    }

    /**
     * 设置页面内css代码，用作于样式微调
     * @param string $scssCode
     * @param string $prevMessage
     * @return $this
     */
    public function style($scssCode = '', $prevMessage = '')
    {
        TplRun::setStyleOrScript($scssCode, $prevMessage);
        return $this;
    }

    /**
     * 设置页面内css代码，用作于样式微调
     * @param string $scssCode
     * @param string $prevMessage
     * @return $this
     */
    public function styleTop($scssCode = '', $prevMessage = '')
    {
        TplRun::setStyleOrScript($scssCode, $prevMessage, 'style', true);
        return $this;
    }
    
    /**
     * 设置页面内js代码，用作于样式微调
     * @param string $jsCode
     * @param string $prevMessage
     * @return $this
     */
    public function script($jsCode = '', $prevMessage = '')
    {
        TplRun::setStyleOrScript($jsCode, $prevMessage, 'script');
        return $this;
    }

    /**
     * 设置页面内js代码，用作于样式微调 （顶部）
     * @param string $jsCode
     * @param string $prevMessage
     * @return $this
     */
    public function scriptTop($jsCode = '', $prevMessage = '')
    {
        TplRun::setStyleOrScript($jsCode, $prevMessage, 'script', true);
        return $this;
    }

    /**
     * 获取插件文件路径 plugins static 动态加载CSS文件
     * @param null $url 指定路径
     * @param bool $isDomain 是否显示域名或IP地址
     * @return array|string
     */
    public function css($dirs = null, $isDomain = true)
    {
        $dirs = $this->getStaticDirs($dirs);
        if (empty($dirs)) {
            return $this;
        }
        Init::getStaticCSS($dirs, $isDomain);
        return $this;
    }


    /**
     * 获取插件文件路径 plugins static 动态加载JS文件
     * @param null $url 指定路径
     * @param bool $isDomain 是否显示域名或IP地址
     * @return array|string
     */
    public function js($dirs = null, $isDomain = true)
    {
        $dirs = $this->getStaticDirs($dirs);
        if (empty($dirs)) {
            return $this;
        }
        Init::getStaticJS($dirs, $isDomain);
        return $this;
    }

    /**
     * 获取短Md5
     * @return bool|string
     */
    private function getSortMd5()
    {
        if (empty($this->md5Sort)) {
            $this->md5Sort = substr($this->md5, 4, 8);
        }
        return $this->md5Sort;
    }

    /**
     * 获取乱序分割字符串
     * @param $runStr
     * @param array $seps
     * @return array
     */
    private function getSplitRun($runStr, $seps = [])
    {
        if (empty($seps) || empty($runStr)) {
            return [$runStr, []];
        }

        $sepKvs = [];
        $useSeps = [];
        foreach ($seps as $sep) {
            $pos = strpos($runStr, $sep);
            if ($pos === false) {
                continue;
            }
            $splits = explode($sep, $runStr);
            $tSeps = [];
            $useSeps[] = $sep;
            foreach ($seps as $tSep) {
                if (!in_array($tSep, $useSeps)) {
                    $tSeps[] = $tSep;
                }
            }
            $splitsLen = count($splits);
            for ($i = 1; $i < $splitsLen; $i ++) {
                $tags = [];
                $v = $splits[$i];
                $min = -1;
                foreach ($tSeps as $ts) {
                    $pos = strpos($v, $ts);
                    if ($pos !== false) {
                        if ($min < 0) {
                            $min = $pos;
                        } elseif ($pos < $min) {
                            $min = $pos;
                        }
                    }
                }
                if ($min >= 0) {
                    $tagName = trim(substr($v, 0, $min));
                    $splits[$i] = substr($v, $min);
                    if (!empty($tagName)) {
                        $sepKvs[$sep] = $tagName;
                    }
                } else {
                    if (!empty($v)) {
                        $sepKvs[$sep] = $v;
                    }
                    $splits[$i] = "";
                }
            }
            $runStr = implode("", $splits);
        }

        return [$runStr, $sepKvs];
    }

    /**
     * 智能生成运行JS
     * @param string $command
     */
    private function __runJs($runStr = '', $args = [])
    {
        $ret = '';
        if (empty($runStr) || !is_string($runStr)) {
            return [$ret, $runStr, $ret];
        }
        $runStr = trim($runStr);
        if (empty($runStr)) {
            return [$ret, $runStr, $ret];
        }

        // : 为函数, = 为插件路径, # 为id名称
        list($dir, $sepKvs) = $this->getSplitRun($runStr, [':', '=', '#']);
        $fun = $sepKvs[':'] ?? '';
        $runJsId = $sepKvs['#'] ?? '';
        $pluDir = $sepKvs['='];
        if (!empty($pluDir)) {
            $dir = "{$dir}=={$pluDir}";
        }
        if (empty($dir) && empty($fun)) {
            return [$ret, $dir, $ret];
        }
        if (!empty($runJsId) && !preg_match('/^[^0-9]\w+$/', $runJsId)) {
            $runJsId = '';
        }
        $runJsIdCreate = Init::getRunJS($this->getDir(), $dir, $fun, $this->getSortMd5(), $args, $runJsId);
        if (empty($runJsId)) {
            if (empty($runJsIdCreate)) {
                return [$ret, $dir, $ret];
            }
            return ["run_{$runJsIdCreate}", $dir, $runJsIdCreate];
        }

        return [$runJsId, $dir, "#{$runJsId}"];
    }

    /**
     * 智能生成运行JS
     * @param string $command
     */
    public function runJs($runStr = '')
    {
        $args = func_get_args();
        $args = array_slice($args, 1);
        list($id, $path, $cacheId) = $this->__runJs($runStr, $args);
        return new PluginRunJs($id, $cacheId, $path, $args, $this);
    }

    /**
     * 获取视图类型路径
     * @param string $srcDir
     * @param int $deep
     * @param string $moreDir
     * @return mixed
     */
    public function getViewDir($srcDir = '', $deep = 0, $moreDir = '')
    {
        $viewDir = "";
        $dir = '';
        list($status, $tDir, $isOnly) = $this->getDirInfo();
        if ($status) {
            $dir = '.' . $tDir;
        }
        $viewDir = "plugins{$dir}.view";
        if (!empty($srcDir)) {
            $srcDir = trim($srcDir, "\\/.");
            if (!empty($srcDir)) {
                $viewDir .= ".{$srcDir}";
            }
        }
        $tpl = $this->tpl;
        empty($tpl) && $tpl = TphpConfig::$plugins['tpl'];
        return $tpl->getViewDir($viewDir, $deep, $moreDir);
    }

    /**
     * 操作其他规则数据库，自定义数据查询
     * @param string $table
     * @param string $conn
     * @return mixed
     */
    public function db($table = "", $conn = "")
    {
        $tpl = $this->tpl;
        empty($tpl) && $tpl = TphpConfig::$plugins['tpl'];
        if (empty($tpl)) {
            return tpl()->db($table, $conn);
        } else {
            return $tpl->db($table, $conn);
        }
    }

    /**
     * ORM 数据库功能
     * @param string $info model相对路径或模型
     * @param string $conn 数据库链接， 如果为空则默认链接，如果为字符串则获取database.php文件获取，如果数组则新增链接
     * @return mixed
     */
    public function model($info = null, $conn = null)
    {
        $reset = [];
        if (is_string($info)) {
            $info = $this->getAutoPath($info, '');
        } elseif (is_array($info)) {
            if (is_string($info['model'])) {
                $iModel = trim($info['model']);
                if (!empty($iModel)) {
                    $reset = $info;
                    $info = $this->getAutoPath($iModel, '');
                    unset($reset['model']);
                }
            }
        }
        return Init::model($info, $conn, $this, $reset);
    }

    /**
     * config 功能配置，与Laravel功能类似
     * @param null $config
     * @param null $default
     * @return mixed
     */
    public function config($config = null, $default = null)
    {
        return Init::config($config, $default, $this);
    }

    /**
     * 获取配置信息，如果插件内未找到则从 Laravel 中继续查找
     * @param string $config
     * @param null $default
     * @return null
     */
    public function getConfig($config = '', $default = null)
    {
        if (!is_string($config)) {
            return $default;
        }
        
        if (empty(trim($config))) {
            return $default;
        }
        
        // 从插件 config 目录中查找
        $data = $this->config($config);
        
        // 如果为找到， 则继续从 Laravel config 目录中查找
        if (is_null($data)) {
            $data = config($config);
        }
        
        if (is_null($data)) {
            $data = $default;
        }
        
        return $data;
    }
}
