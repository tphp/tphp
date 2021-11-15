<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tphp\Domains\DomainsController;
use Illuminate\Support\ServiceProvider;
use Tphp\Basic\Tpl\Handle as TplHandle;
use Tphp\Config as TphpConfig;

// 设置dump
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextualizedDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

// 默认时区设置为中国上海
date_default_timezone_set(env("TIMEZONE", 'Asia/Shanghai'));

class Routes
{

    /**
     * 重新设置dump函数，避免打印出错
     */
    private static function setDump()
    {
        VarDumper::setHandler(function ($var){
            if (!Register::$isDump) {
                Register::$isDump = true;
            }
            $cloner = new VarCloner();
            $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);
            if (isset($_SERVER['VAR_DUMPER_FORMAT'])) {
                $dumper = 'html' === $_SERVER['VAR_DUMPER_FORMAT'] ? new HtmlDumper() : new CliDumper();
            } else {
                $dumper = \in_array(\PHP_SAPI, ['cli', 'phpdbg']) ? new CliDumper() : new HtmlDumper();
            }
            $dumper = new ContextualizedDumper($dumper, [new SourceContextProvider()]);
            $dumper->dump($cloner->cloneVar($var));
        });
    }

    /**
     * 重新修复env()获取getenv()取不到值的BUG
     */
    private static function repairEnv()
    {
        $envFile = base_path('.env');
        if (!is_file($envFile)) {
            return;
        }

        $envStr = "";
        $fileHandle = fopen($envFile, "r");
        while (!feof($fileHandle)) {
            $line = fgets($fileHandle);
            $envStr .= $line;
        }
        fclose($fileHandle);

        if (empty($envStr)) {
            return;
        }

        $envArray = explode("\n", $envStr);
        $envSets = [];
        foreach ($envArray as $ea) {
            $ea = trim($ea);
            if (empty($ea)) {
                continue;
            }
            $pos = strpos($ea, '=');
            if ($pos === false) {
                continue;
            }
            $envKey = trim(substr($ea, 0, $pos));
            if (empty($envKey) || isset($_ENV[$envKey])) {
                continue;
            }
            $envValue = trim(substr($ea, $pos + 1));
            $envSets[] = "{$envKey}={$envValue}";
        }

        if (empty($envSets)) {
            return;
        }

        self::setEnv($envSets);
    }

    /**
     * 获取文件
     * @param $file
     * @return bool|mixed
     */
    private static function includeFile($file)
    {
        if (file_exists($file)) {
            return include $file;
        }

        return null;
    }
    
    /**
     * 设置默认配置
     */
    private static function setConfig()
    {
        $vPaths = config("view.paths");
        if (empty($vPaths)) {
            $vPaths = [];
        }
        $tbPath = Register::getHtmlPath(true);
        if ($tbPath) {
            $vPaths[] = $tbPath;
        }

        $viewPaths = Register::$viewPaths;
        $viewPaths[] = dirname(__DIR__);
        $viewPaths = array_unique($viewPaths);
        Register::$viewPaths = $viewPaths;

        foreach ($viewPaths as $vp) {
            $vp .= "/html";
            if (is_dir($vp)) {
                $vPaths[] = $vp;
            }
        }
        config(["view.paths" => $vPaths]);
    }

    /**
     * 获取 domains.php 配置
     * 主路径 /config/domains.php 优先
     * 插件配置路径 /html/plugins/模板路径/config/domains.php
     * @return array
     */
    private static function getDomains()
    {
        $pluDomains = [];
        $flag = "__#plu#__";
        $xFile = import("XFile");
        $pluPath = Register::getHtmlPath(true) . "/plugins/";
        $pluDirs = $xFile->getDirs($pluPath);
        foreach ($pluDirs as $topDir) {
            $inPath = "{$pluPath}{$topDir}/";
            $inDirs = $xFile->getDirs($inPath);
            foreach ($inDirs as $subDir) {
                $domainsFile = "{$inPath}{$subDir}/config/domains.php";
                if (!is_file($domainsFile)) {
                    continue;
                }
                $dms = self::includeFile($domainsFile);
                if (empty($dms) || !is_array($dms)) {
                    continue;
                }
                $pluName = "{$topDir}.{$subDir}";
                foreach ($dms as $key => $val) {
                    if (is_string($key) && !empty($val) && is_array($val)) {
                        $key = trim($key);
                        if (!empty($key)) {
                            $val[$flag] = $pluName;
                            $pluDomains[$key] = $val;
                        }

                    }
                }
            }
        }

        foreach ($pluDomains as $key => $val) {
            $pluName = $val[$flag];
            unset($val[$flag]);
            $tpl = $val['tpl'];
            if (empty($tpl) || !is_string($tpl)) {
                $tpl = ":{$pluName}";
            } elseif ($tpl[0] !== ':') {
                $tpl = str_replace("\\", ".", $tpl);
                $tpl = str_replace("/", ".", $tpl);
                $tpl = trim($tpl, ".");
                $tpl = ":{$pluName}.{$tpl}";
            }
            $val['tpl'] = $tpl;
            $pluDomains[$key] = $val;
        }

        $domains = [];
        $domainsSrc = config("domains");
        if (is_array($domainsSrc)) {
            foreach ($domainsSrc as $key => $val) {
                if (!is_string($key) || !is_array($val)) {
                    continue;
                }
                $key = trim($key);
                if (empty($key)) {
                    continue;
                }
                $domains[$key] = $val;
            }
        }

        foreach ($pluDomains as $key => $val) {
            $domains[$key] = $val;
        }
        return $domains;
    }

    /**
     * 重新解析域名
     */
    private static function setDomains(&$domainRoutes, $hhArr)
    {
        $domains = self::getDomains();
        if (is_array($domains)) {
            foreach ($domains as $key => $val) {
                $keys = explode("|", $key);
                foreach ($keys as $v) {
                    if (empty($v)) {
                        continue;
                    }
                    $v = strtolower(trim(trim($v), "\."));
                    $_replace_ = [];
                    if (strpos($v, "*") !== false || strpos($v, "{") !== false) {
                        // 域名泛解析
                        $vArr = explode(".", $v);
                        foreach ($vArr as $kk => $vv) {
                            $hhNow = $hhArr[$kk];
                            if (!isset($hhNow)) {
                                continue;
                            }
                            $vv = trim($vv);
                            $vvLen = strlen($vv);
                            if ($vvLen <= 0) {
                                continue;
                            }
                            if ($vv == '*') {
                                $vArr[$kk] = $hhNow;
                            } elseif ($vv[0] == '{' && $vv[$vvLen - 1] == '}') {
                                $vvVar = substr($vv, 1, $vvLen - 2);
                                if (preg_match('/^\w+$/', $vvVar)) {
                                    $vArr[$kk] = $hhNow;
                                    $_replace_[$vv] = $hhNow;
                                }
                            }
                        }
                        $v = implode(".", $vArr);
                    }
                    $domainRoutes[$v] = $val;
                    if (!empty($_replace_)) {
                        $domainRoutes[$v]['_replace_'] = $_replace_;
                    }
                }
            }
        }
        config(["domains" => $domainRoutes]);
    }

    /**
     * 设置env key value
     * @param $envKey
     * @param $envValue
     */
    private static function setEnvKeyValue($envKey, $envValue)
    {
        $_ENV[$envKey] = $envValue;
        $_SERVER[$envKey] = $envValue;
    }

    /**
     * 设置env
     * @param $env
     */
    private static function setEnv($env)
    {
        $setEnvs = [];
        foreach ($env as $key => $val) {
            $envKey = '';
            $envValue = '';
            if (is_int($key)) {
                if (!is_string($val)) {
                    continue;
                }
                $eqPos = strpos($val, "=");
                if ($eqPos === false || $eqPos <= 0) {
                    continue;
                }
                $setEnvs[] = $val;
                $envKey = substr($val, 0, $eqPos);
                $envValue = substr($val, $eqPos + 1);
            } elseif (is_string($val) || is_numeric($val)) {
                $setEnvs[] = "{$key}={$val}";
                $envKey = $key;
                $envValue = $val;
            } elseif (is_bool($val)) {
                if ($val) {
                    $setEnvs[] = "{$key}=true";
                } else {
                    $setEnvs[] = "{$key}=false";
                }
                $envKey = $key;
                $envValue = $val;
            }
            if (empty($envKey)) {
                continue;
            }

            if (!is_string($envValue)) {
                self::setEnvKeyValue($envKey, $envValue);
                continue;
            }

            $envValueLen = strlen($envValue);
            if ($envValueLen <= 2) {
                self::setEnvKeyValue($envKey, $envValue);
                continue;
            }
            $isChange = true;
            if ($envValue[0] == '"' && $envValue[$envValueLen - 1] == '"') {
                $envValue = substr($envValue, 1, $envValueLen - 2);
            } elseif ($envValue[0] == "'" && $envValue[$envValueLen - 1] == "'") {
                $envValue = substr($envValue, 1, $envValueLen - 2);
                $isChange = false;
            }

            if (!$isChange) {
                self::setEnvKeyValue($envKey, $envValue);
                continue;
            }

            // 动态解析字符串
            $flag = "\${";
            $envSplit = explode($flag, $envValue);
            if (count($envSplit) < 2) {
                self::setEnvKeyValue($envKey, $envValue);
                continue;
            }

            foreach ($envSplit as $epKey => $ep) {
                if (empty($ep) || $epKey < 1) {
                    continue;
                }
                $pos = strpos($ep, "}");
                if ($pos === false) {
                    $envSplit[$epKey] = $flag . $ep;
                    continue;
                }
                $changStr = substr($ep, 0, $pos);
                if (!isset($_ENV[$changStr])) {
                    $envSplit[$epKey] = $flag . $ep;
                    continue;
                }
                $envSplit[$epKey] = $_ENV[$changStr] . substr($ep, $pos + 1);
            }
            $envValue = implode("", $envSplit);

            self::setEnvKeyValue($envKey, $envValue);
        }
        foreach ($setEnvs as $se) {
            putenv($se);
        }
    }

    /**
     * 获取小写键值数组
     * @param null $array
     * @return array
     */
    private static function getKeyLower($array = null)
    {
        $ret = [];
        if (empty($array) || !is_array($array)) {
            return $ret;
        }

        foreach ($array as $key => $val) {
            if (!is_string($key)) {
                $ret[$key] = $val;
                continue;
            }
            $key = trim($key);
            if (empty($key)) {
                continue;
            }
            $ret[strtolower($key)] = $val;
        }
        return $ret;
    }

    /**
     * 动态变量解析
     * @param array $domainConfig
     * @return array
     */
    private static function getRouteReplace($domainConfig = [])
    {
        $domainConfig = self::getKeyLower($domainConfig);
        $_replace_ = $domainConfig['_replace_'];
        if (!isset($_replace_)) {
            return $domainConfig;
        }
        unset($domainConfig['_replace_']);
        foreach ($domainConfig as $key => $val) {
            if (!is_string($val)) {
                continue;
            }
            foreach ($_replace_ as $k => $v) {
                $val = str_replace($k, $v, $val);
            }
            $domainConfig[$key] = $val;
        }
        return $domainConfig;
    }

    /**
     * 设置路由规则
     */
    private static function setRoute()
    {
        /**
         * 先分配ICON、JS和CSS生成模块
         */
        //公共模板CSS
        Route::get('/static/tpl/css/{md5}.css', function () {
            return (new TplHandle())->css();
        });
        //公共模板JS
        Route::get('/static/tpl/js/{md5}.js', function () {
            return (new TplHandle())->js();
        });
        //默认图标favicon.ico
        Route::any('/favicon.ico', function () {
            return (new TplHandle())->ico();
        });

        /**
         * 自动路由分配
         * 1、对config("domains")中的配置进行路由
         * 2、优先处理config("domains")中的路由配置
         * 3、如果配置中不存在则从Apcu模块中获取对应位置
         */
        $hh = $_SERVER['HTTP_HOST'];
        $hhInfo = explode(":", $hh);
        $hhPort = '';
        $hhs = [];
        if (count($hhInfo) > 1) {
            $hhs[] = $hh;
            list($hh, $hhPort) = $hhInfo;
        }
        $hhArr = explode(".", $hh);
        $hhStr = "";
        foreach ($hhArr as $val) {
            if (empty($hhStr)) {
                $hhStr = $val;
            } else {
                $hhStr .= "." . $val;
            }
            $hhs[] = $hhStr;
        }
        rsort($hhs);

        $domainRoutes = [];
        self::setDomains($domainRoutes, $hhArr);

        $dm = "";
        foreach ($hhs as $val) {
            if (isset($domainRoutes[$val])) {
                $dm = $val;
                break;
            }
        }
        if (!empty($dm) && !empty($domainRoutes[$dm])) {
            $pluDir = '';
            $drd = $domainRoutes[$dm];
            $drd = self::getRouteReplace($drd);
            $help = $drd['help'];
            unset($drd['callback']);
            if (!empty($help)) {
                if (is_string($help)) {
                    $help = trim($help);
                    if ($help == ':') {
                        // 如果设置在插件内
                        $tplHelp = trim($drd['tpl']);
                        if ($tplHelp[0] == ':') {
                            $help = ltrim($tplHelp, " :");
                        } else {
                            $help = '';
                        }
                    }

                    $help = str_replace("\\", "/", $help);
                    $help = str_replace(".", "/", $help);
                    $help = strtolower($help);
                    $help = trim(trim($help), "/");
                } else {
                    $help = '';
                }
                $drd['help'] = $help;
                if (empty($drd['plu']) || !is_array($drd['plu'])) {
                    $drd['plu'] = [];
                }
                $drd['plu']['dir'] = $help;
            }

            $isPlu = false;
            if (empty($help)) {
                $tpl = trim($drd['tpl']);
                if ($tpl[0] == ':') {
                    $isPlu = true;
                    $tpl = ltrim($tpl, " :");
                }
                $tpl = str_replace(".", "/", $tpl);
                $tpl = str_replace("\\", "/", $tpl);
                $tpl = trim(trim($tpl), "\\/");
                // 指向目录为插件目录
                if ($isPlu) {
                    $tplArr = explode("/", $tpl);
                    if (count($tplArr) < 2) {
                        __exit("Plugins Html： {$tpl} Format Is Error");
                    }
                    $pluDir = "{$tplArr[0]}/{$tplArr[1]}";
                    unset($tplArr[0]);
                    unset($tplArr[1]);
                    $tpl = "plugins/{$pluDir}/html";
                    if (!empty($tplArr)) {
                        $tpl .= "/" . implode("/", $tplArr);
                    }
                }
                $drd['tpl'] = $tpl;
            } else {
                $tpl = '?';
            }

            $drdTpl = $tpl;
            $dmDataFile = Register::getHtmlPath(true) . "/{$drdTpl}/data.php";
            if (is_file($dmDataFile)) {
                $extData = self::includeFile($dmDataFile);
                if (!empty($extData) && is_array($extData)) {
                    $extData = self::getKeyLower($extData);
                    if (isset($extData['tpl'])) {
                        unset($extData['tpl']);
                    }
                    foreach ($extData as $extKey => $extVal) {
                        if (is_null($extVal) || !is_string($extKey)) {
                            continue;
                        }
                        /*
                         * $extKey 参数说明
                         *
                         * 'conn', // 数据库链接标识
                         * 'title', // 页面标题
                         * 'keywords', // 页面关键词
                         * 'description', // 页面描述
                         * 'args', // URL前置参数绑定设置 如 /name/subname
                         * 'go', // URL跳转链接
                         * 'layout', //全局布局，优先级最低 如 public/tpl
                         * 'icon', // 网站图标设置，支持jpeg、png、jpg、ico等，默认：favicon.ico
                         * 'routes', // 原始路由
                         * 'env', // .env配置
                         * 'plu', // 插件配置
                         */
                        $extKey = strtolower(trim($extKey));

                        // 如果键前缀为 ':'， 如果上级已设置，则以上级设置为准
                        if ($extKey[0] == ':') {
                            $extKey = trim($extKey, ": ");
                            if (isset($drd[$extKey])) {
                                continue;
                            }
                        }

                        if ($extKey == 'routes') { // 路由设置
                            if (empty($drd[$extKey])) {
                                $drd[$extKey] = $extVal;
                            } else {
                                foreach ($extVal as $evk => $evv) {
                                    if (empty($evv) || !is_array($evv)) {
                                        continue;
                                    }
                                    if (empty($drd[$extKey][$evk])) {
                                        $drd[$extKey][$evk] = [];
                                    }
                                    foreach ($evv as $k => $v) {
                                        if (!empty($v)) {
                                            $drd[$extKey][$evk][$k] = $v;
                                        }
                                    }
                                }
                            }
                        } elseif ($extKey == 'env') { // 路由设置
                            if (empty($drd[$extKey])) {
                                $drd[$extKey] = $extVal;
                            } elseif (is_array($extVal)) {
                                foreach ($extVal as $evk => $evv) {
                                    if (is_int($evk)) {
                                        $drd[$extKey][] = $evv;
                                    } else {
                                        $drd[$extKey][$evk] = $evv;
                                    }
                                }
                            }
                        } elseif ($extKey == 'plu') { // 插件设置
                            $drd[$extKey] = TplHandle::getPluginsConfig($drd[$extKey], $extVal);
                        } else {
                            $drd[$extKey] = $extVal;
                        }
                    }
                }
            } elseif (!empty($drd['go'])) {
                // 如果设置 url 则直接跳转
                ( new DomainsController())->gotoUrl($drd['go'], $drd['go_post_message']);
            }

            foreach ($drd as $key => $val) {
                if (is_function($val) && $key !== 'call') {
                    $drd[$key] = $val();
                }
            }

            // 如果已指向插件目录，则强制使用插件路径
            if (!empty($pluDir)) {
                if (empty($drd['plu'])) {
                    $drd['plu'] = [];
                }
                $drd['plu']['dir'] = $pluDir;
            }

            $drdIcon = $drd['icon'];
            if (!empty($drdIcon)) { // 图标设置
                if (is_string($drdIcon)) {
                    $drdIcon = trim($drdIcon);
                    $isPluIcon = false;
                    // 插件设置
                    if ($drdIcon[0] == ':') {
                        $drdIcon = ltrim($drdIcon, " :");
                        $isPluIcon = true;
                    }
                    $drdIcon = str_replace("\\", "/", $drdIcon);
                    $drdIcon = trim(trim($drdIcon), '/');
                    if (empty($drdIcon)) {
                        unset($drd['icon']);
                    } else {
                        if ($isPluIcon) {
                            if (isset($drd['plu']) && is_string($drd['plu']['dir'])) {
                                $drd['icon'] = "static/plugins/{$drd['plu']['dir']}/{$drdIcon}";
                            } else {
                                unset($drd['icon']);
                            }
                        } else {
                            $drd['icon'] = $drdIcon;
                        }
                    }
                } else {
                    unset($drd['icon']);
                }
            }

            $drd['key'] = $dm;

            // 删除配置空值
            $unsets = [];
            foreach ($drd as $k => $v) {
                if (is_null($v)) {
                    $unsets[] = $k;
                }
            }

            foreach ($unsets as $us) {
                unset($drd[$us]);
            }

            // 页面配置
            $domainRoutes[$dm] = $drd;
            TphpConfig::$domain = $drd;
            TphpConfig::$domains = $domainRoutes;
            if (!empty($tpl) && is_null(Register::$topPath)) {
                Register::$topPath = $tpl;
                $routes = $drd['routes'];
                empty($routes) && $routes = [];

                $routesBools = [];
                foreach ($routes as $key => $val) {
                    foreach ($val as $k => $v) {
                        if ($routesBools[$k]) break;
                        $routesBools[$k] = true;
                    }
                }

                $pluObj = null;
                // 插件全局配置
                if ($isPlu && !empty($pluDir)) {
                    $pluObj = plu($pluDir);
                    $pluEnv = $pluObj->config('env');
                    if (!empty($pluEnv) && is_array($pluEnv)) {
                        self::setEnv($pluEnv);
                    }
                }

                // 模板全局配置
                $env = $drd['env'];
                if (!empty($env) && is_array($env)) {
                    self::setEnv($env);
                }

                // 需要合并到 Laravel config 目录的数据
                if (!empty($pluObj)) {
                    $mergeConfigs = [
                        'database.connections'
                    ];
                    foreach ($mergeConfigs as $mergeConfig) {
                        $mcData = $pluObj->config($mergeConfig);
                        if (empty($mcData) || !is_array($mcData)) {
                            continue;
                        }

                        $cfg = config($mergeConfig);
                        if (empty($cfg)) {
                            $cfg = [];
                        }
                        foreach ($mcData as $key => $val) {
                            $cfg[$key] = $val;
                        }
                        if (!empty($cfg)) {
                            config([
                                $mergeConfig => $cfg
                            ]);
                        }
                    }
                }

                // 加载Session中间件，使Session缓存起作用
                $middleware = [
                    \Illuminate\Session\Middleware\StartSession::class,
                    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                ];

                $mList = [
                    // 全局中间件
                    config("middleware"),
                    // 局部中间件
                    $drd["middleware"]
                ];

                foreach ($mList as $ml) {
                    if (is_array($ml)) {
                        foreach ($ml as $mlVal) {
                            if (is_string($mlVal)) {
                                $middleware[] = $mlVal;
                            }
                        }
                    }
                }

                $middleware = array_unique($middleware);

                $routeMod = Route::domain($hh);
                if (count($routes) > 0) {
                    //优先根据指定路径
                    $routeMod->group(function () use ($routes, $middleware) {
                        foreach ($routes as $key => $val) {
                            if (in_array($key, ['any', 'delete', 'get', 'options', 'patch', 'post', 'put', 'redirect', 'view'])) {
                                foreach ($val as $k => $v) {
                                    Route::$key($k, $v)->middleware($middleware);
                                }
                            }
                        }
                    });
                }

                //优先根据指定路径
                $routeMod->group(function () use ($routesBools, $middleware, $help) {
                    $addStr = "";
                    !$routesBools['/'] && Route::get('/', function () {
                        return (new DomainsController())->tpl();
                    })->middleware($middleware);

                    $ru = str_replace("\\", "/", $_SERVER['REQUEST_URI']);
                    $ruQuerys = explode('?', $ru);
                    $ru = $ruQuerys[0];
                    $ru = explode('#', $ru)[0];
                    $ru = trim(trim($ru, '/'));

                    // 处理 /index.php/
                    $ruSetps = explode("/", $ru);
                    if (count($ruSetps) > 1) {
                        if ("/{$ruSetps[0]}" == $_SERVER['SCRIPT_NAME']) {
                            unset($ruSetps[0]);
                            $ru = implode("/", $ruSetps);
                        }
                    }

                    TphpConfig::$domain['url'] = $ru;
                    if (empty($ru)) {
                        $ruArr = [];
                    } else {
                        $ruArr = explode("/", $ru);
                    }
                    $ruCot = count($ruArr);
                    for ($i = 1; $i <= $ruCot; $i++) {
                        $addStr .= '/{api_name_' . $i . '}';
                    }
                    if (!$routesBools[$addStr]) {
                        $ruSubStr = $ruCot >= 2 ? "{$ruArr[0]}/{$ruArr[1]}" : "";
                        if ($ruSubStr == 'static/plugins') {
                            // 公共插件 生成文件软链接
                            Route::any($addStr, function () use ($ruArr) {
                                return (new TplHandle())->pluginsStatic($ruArr[2], $ruArr[3], implode("/", array_slice($ruArr, 4)));
                            })->middleware($middleware);
                        }  elseif ($ruSubStr == 'help/plugins') {
                            // 公共插件 帮助文档
                            unset($ruArr[0]);
                            unset($ruArr[1]);
                            $ruArr = array_values($ruArr);
                            Route::any($addStr, function () use ($ruArr) {
                                return (new TplHandle())->pluginsHelp($ruArr);
                            })->middleware($middleware);
                        } elseif(!empty($help)) {
                            // 公共插件 帮助文档
                            $help_arr = explode("/", $help);
                            if (count($help_arr) !== 2) {
                                __exit("Plugins Help： {$help} is not setting");
                            }
                            $ruArr = array_merge($help_arr, $ruArr);
                            Route::any($addStr, function () use ($ruArr) {
                                return (new TplHandle())->pluginsHelp($ruArr, true);
                            })->middleware($middleware);
                        } elseif ($ruArr[0] == 'storage') {
                            // 创建storage软链接
                            unset($ruArr[0]);
                            $ruArr = array_values($ruArr);
                            Route::any($addStr, function () use ($ruArr) {
                                return (new TplHandle())->publicStorage($ruArr);
                            })->middleware($middleware);
                        } else {
                            // 主页面入口
                            Route::any($addStr, function () {
                                return (new DomainsController())->tpl();
                            })->middleware($middleware);
                        }
                    }
                });
            }
        }
    }

    /**
     * 路由入口
     */
    public static function set()
    {
        self::setDump();
        self::repairEnv();
        self::setConfig();
        self::setRoute();
    }
}
