<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl\Handle;

use Illuminate\Support\Facades\Cache;
use Tphp\Basic\JSMin\JSMin;
use Tphp\Basic\Plugin\Init as PluginInit;
use Tphp\Basic\Tpl\Run;
use Tphp\Register;
use Tphp\Config as TphpConfig;

/**
 * 插件
 * Trait Plugin
 * @package Tphp\Basic\Tpl\Handle
 */
trait Plugin
{
    /**
     * 获取 @$remark 文件路径, 只取第一个注释中的@$remark解析, 注释解析在符号 \/****\/ 中
     * @param $str
     * @param $remark import 和 static 模式
     * @return array
     */
    private static function __getRemarkInfo($str, $remarks = [])
    {
        $returns = [];
        $pos = strpos($str, "/*");
        if ($pos !== 0) {
            return [$returns, $str];
        }

        $pos = strpos($str, "*/");
        if ($pos === false) {
            return [$returns, $str];
        }

        $remarkStr = trim(substr($str, 2, $pos - 2));
        $str = trim(substr($str, $pos + 2));

        $remarkStr = trim(str_replace("*", "", $remarkStr));
        if (empty($remarkStr)) {
            return [$returns, $str];
        }

        foreach ($remarks as $remark) {
            $remarkArr = explode("@{$remark}", $remarkStr);
            if (count($remarkArr) > 0) {
                unset($remarkArr[0]);
            }
            $returns[$remark] = [];
            foreach ($remarkArr as $ia) {
                if (!in_array($ia[0], [" ", "\t"])) {
                    continue;
                }
                $ia = trim($ia);
                if (empty($ia)) {
                    continue;
                }

                if ($remark == 'import') {
                    $pos = strpos($ia, ":");
                    if ($pos !== false) {
                        $iptType = strtolower(trim(substr($ia, 0, $pos)));
                        if (in_array($iptType, ['js', 'css'])) {
                            $ia = "{$iptType}:" . trim(substr($ia, $pos + 1));
                        }
                    }
                }

                $ia = str_replace("\r", " ", $ia);
                $ia = str_replace("\n", " ", $ia);
                $ia = str_replace("\t", " ", $ia);
                $pos = strpos($ia, " ");
                if ($pos > 0) {
                    $ia = substr($ia, 0, $pos);
                }
                $ia = str_replace("\\", "/", $ia);
                if (!self::isUrl($ia)) {
                    $ia = str_replace("///", "/", $ia);
                    $ia = str_replace("//", "/", $ia);
                    $ia = rtrim($ia, "/");
                }
                $returns[$remark][] = $ia;
            }
        }
        return [$returns, $str];
    }

    /**
     * 解析Css和Js
     * @param $rootPath 根路径
     * @param $dir 指定路径
     * @param $type 文件类型Scss 或 Js
     * @param $pluDir Plu路径
     * @param array $paths @import 路径
     * @param array $staticPaths @static 路径
     * @param bool $isRun 是否是动态加载JS
     */
    private static function __getPluginsScssSjs($rootPath, $dir, $type, $pluDir, &$paths = [], &$staticPaths = [], $isRun = false)
    {
        $pos = strpos($dir, "=");
        if ($type === 'scss') {
            $typeExt = 'scss';
        } else {
            $typeExt = 'sjs';
        }
        if ($pos !== false) {
            if ($pos <= 0) {
                return;
            }
            $pDir = trim(substr($dir, $pos), "=");
            if (!empty($pDir)) {
                $pDir = str_replace("\\", "/", $pDir);
                $pDir = str_replace(".", "/", $pDir);
                $pDir = str_replace("//", "/", $pDir);
                $pDir = trim($pDir, "/");
            }
            if (!empty($pDir)) {
                $pDirArr = explode("/", $pDir);
                if (count($pDirArr) !== 2) {
                    return;
                }
            }
            $pluDir = $pDir;
            $rootPath = PluginInit::getPluginDir($pluDir);
            $dir = substr($dir, 0, $pos);
        }
        if (!isset($paths[$type])) {
            $paths[$type] = [];
        }
        if (!isset($paths[$type][$pluDir])) {
            $paths[$type][$pluDir] = [];
        }
        if (isset($paths[$type][$pluDir][$dir])) {
            return;
        }

        $filePaths = [];
        $browser = self::$browser;
        $filePaths["{$rootPath}/{$dir}/view.{$type}"] = false;
        $filePaths["{$rootPath}/{$dir}/view.{$browser}.{$type}"] = true;
        $selectPaths = [];
        foreach ($filePaths as $filePath => $isBrowser) {
            if (is_file($filePath)) {
                $selectPaths[$filePath] = $isBrowser;
            }
        }

        if (empty($selectPaths)) {
            return;
        }

        $sTexts = [];
        $paths[$type][$pluDir][$dir] = [];

        foreach ($selectPaths as $filePath => $isBrowser) {
            $sText = trim(self::xFile()->read($filePath));
            list($remarks, $sText) = self::__getRemarkInfo($sText, ['import', 'static']);
            $imports = $remarks['import'];
            if (empty($imports)) {
                $imports = [];
            }
            $statics = $remarks['static'];
            if (!empty($statics)) {
                $typeLen = strlen($typeExt);
                foreach ($statics as $static) {
                    if (empty($static)) {
                        continue;
                    }
                    if (substr($static, 0, $typeLen + 1) === $typeExt . "/") {
                        $s = trim(substr($static, $typeLen + 1));
                        if (!empty($s)) {
                            $imports[] = substr($static, $typeLen + 1);
                        }
                        continue;
                    }

                    if (!isset($staticPaths[$pluDir])) {
                        $staticPaths[$pluDir] = [];
                    }
                    $staticPaths[$pluDir][$static] = true;

                }
            }
            $baseDir = "";
            $pos = strrpos($dir, "/");
            if ($pos > 0) {
                $baseDir = substr($dir, 0, $pos + 1);
            }
            foreach ($imports as $imp) {
                $reType = $type;
                $isRunBool = false;

                $pos = strpos($imp, ":");
                $impType = "";
                if ($pos !== false) {
                    $impType = substr($imp, 0, $pos);
                    if (in_array($impType, ['css', 'js'])) {
                        $imp = trim(substr($imp, $pos + 1));
                        if ($impType === 'css') {
                            $reType = 'scss';
                        } else {
                            $reType = 'js';
                        }
                        $isRunBool = true;
                    }
                }

                if ($imp[0] === '/') {
                    $_dir = ltrim($imp, "/");
                } elseif ($imp[0] === '.' && $imp[1] === '.' && $imp[2] === '/') {
                    $baseDirLast = $dir;
                    if (empty($baseDirLast)) {
                        continue;
                    }
                    $isBreak = false;
                    while ($imp[0] === '.' && $imp[1] === '.' && $imp[2] === '/') {
                        if ($isBreak) {
                            break;
                        }
                        $imp = substr($imp, 3);
                        $pos = strrpos($baseDirLast, "/");
                        if ($pos === false) {
                            $isBreak = true;
                            break;
                        }
                        $baseDirLast = substr($baseDirLast, 0, $pos);
                    }
                    if ($isBreak) {
                        continue;
                    }
                    $pos = strrpos($baseDirLast, "/");
                    if ($pos > 0) {
                        $baseDirLast = substr($baseDirLast, 0, $pos + 1);
                    } else {
                        $baseDirLast = "";
                    }
                    $_dir = $baseDirLast . $imp;
                } elseif (strpos($imp, "=") === false) {
                    $_dir = $baseDir . $imp;
                } else {
                    $_dir = $imp;
                }

                if (strpos($_dir, "./") !== false) {
                    continue;
                }
                if ($isRunBool) {
                    $rPath = PluginInit::getPluginDir($pluDir);
                } else {
                    $rPath = $rootPath;
                }
                self::__getPluginsScssSjs($rPath, $_dir, $reType, $pluDir, $paths, $staticPaths, $isRun);
            }

            if ($isBrowser) {
                $sIndex = $browser;
            } else {
                $sIndex = '#';
            }

            if ($isRun) {
                if (!isset(TphpConfig::$obStart['imports'])) {
                    TphpConfig::$obStart['imports'] = [];
                }
                $_imports = &TphpConfig::$obStart['imports'];
                if ($type == 'scss') {
                    $loadName = 'runcss_loads';
                } else {
                    $loadName = 'runjs_loads';
                }
                if (!isset($_imports[$loadName])) {
                    $_imports[$loadName] = [];
                }
                $runLoads = &$_imports[$loadName];
                if (!isset($runLoads[$pluDir])) {
                    $runLoads[$pluDir] = [];
                }
                $runLoads[$pluDir][$dir] = true;
                if (!isset($paths[$type][$pluDir][$dir][$sIndex])) {
                    $paths[$type][$pluDir][$dir][$sIndex] = [$sText];
                }
            } else {
                $paths[$type][$pluDir][$dir][$sIndex] = $sText;
            }
        }
    }

    /**
     * 获取Scss文件解析或JS文件解析
     * @param $rootPath 根路径
     * @param $dir 指定路径
     * @param $type 文件类型Scss 或 Js
     * @param $pluDir Plu路径
     * @return string|void
     */
    private function getPluginsScssSjs($rootPath, $dir, $type, $pluDir)
    {
        $browser = self::$browser;
        $filePath = "{$rootPath}/{$dir}/view.{$type}";
        $fileBrowserPath = "{$rootPath}/{$dir}/view.{$browser}.{$type}";
        if (!is_file($filePath) && !is_file($fileBrowserPath)) {
            return;
        }

        $paths = [];
        $staticPaths = [];
        self::__getPluginsScssSjs($rootPath, $dir, $type, $pluDir, $paths, $staticPaths);

        $texts = [];
        foreach ($paths[$type] as $key => $vals) {
            $static = $this->getStaticPath($key);
            foreach ($vals as $bKey => $val) {
                foreach ($val as $pKey => $pVal) {
                    // 如果不为字符串或数据为空时不显示字符
                    if (!is_string($pVal) || empty(trim($pVal))) {
                        continue;
                    }
                    $bKeyName = "{$key}: {$bKey}";
                    if ($pKey !== '#') {
                        $bKeyName .= ".{$pKey}";
                    }

                    $pVal = $this->getReplaceStatic($pVal, $static);
                    if ($type === 'scss') {
                        $pVal = \Tphp\Scss\Run::getCode($pVal, $bKeyName);

                        if (empty(trim($pVal))) {
                            continue;
                        }
                    }

                    if ($this->tplCache) {
                        $texts[] = $pVal;
                    } else {
                        $pFlag = $this->getRemarkFlag($bKeyName);
                        $pVal = trim($pVal);
                        $texts[] = "/*{$pFlag} {$bKeyName} {$pFlag}*/\n\n{$pVal}\n\n";
                    }
                }
            }
        }
        return implode("", $texts);
    }

    /**
     * 解析SCSS文件或JS文件
     * @param $fileName
     * @param $pathStep
     */
    private function pluginsScssSjs($fileName, $pathStep, $pluDir)
    {
        $pos = strpos($fileName, $pathStep);
        if ($pos <= 0) {
            return;
        }

        $fileName = str_replace("\\", "/", $fileName);
        $dirFirst = strtolower(substr($fileName, 0, $pos));
        if ($dirFirst === 'scss') {
            $inArray = ['css', 'scss'];
            $flag = 'scss';
        } elseif ($dirFirst === 'sjs') {
            $inArray = ['js'];
            $flag = 'js';
        } else {
            return;
        }

        if ($flag === 'scss') {
            self::obTplCss();
        } else {
            self::obTplJs();;
        }

        $dirMiddle = substr($fileName, $pos + 1);
        $pos = strrpos($dirMiddle, '.');
        if ($pos > 0) {
            $dirExt = strtolower(substr($dirMiddle, $pos + 1));
            if (!in_array($dirExt, $inArray)) {
                return;
            }
            $sDir = trim(substr($dirMiddle, 0, $pos));
        } else {
            $sDir = $dirMiddle;
        }

        if (empty($sDir)) {
            return;
        }

        $sysPluginsPath = PluginInit::getPluginDir($pluDir);
        if ($this->tplCache) {
            $md5 = substr(md5($sDir), 8, 24);
            $cacheId = "plu_{$flag}_{$md5}";
            $codes = Cache::get($cacheId);
            if ($codes['tag']) {
                $sText = $codes['code'];
            } else {
                $sText = $this->getPluginsScssSjs($sysPluginsPath, $sDir, $flag, $pluDir);
                if ($sText === null) {
                    return;
                }

                if ($flag === 'scss') {
                    $sText = $this->cssZip($sText);
                } else {
                    $sText = JSMin::minify($sText);
                }
                Cache::put($cacheId, [
                    'tag' => true,
                    'code' => $sText
                ], 60 * 60);
            }
        } else {
            $sText = $this->getPluginsScssSjs($sysPluginsPath, $sDir, $flag, $pluDir);
            if ($sText === null) {
                return;
            }
        }
        return $sText;
    }

    /**
     * 获取菜单信息
     * @param $path
     * @param array $menus
     * @param array $paths
     */
    private function getPluginsHelpMenus($path, &$menus = [], &$paths = [], &$indexs = [])
    {
        $id = $menus['id'];
        if (empty($id)) {
            list($childs, $files) = $this->getPluginsHelpDirsFiles($path);
            $paths['default'] = [
                'dirs' => $childs,
                'files' => $files
            ];
        } else {
            $childs = $paths[$id]['dirs'];
            $files = $paths[$id]['files'];
        }

        $title = '';
        if (!empty($files['title'])) {
            $titlePath = "{$path}/{$files['title']}";
            if (is_file($titlePath)) {
                $title = self::xFile()->read($titlePath);
            }
        }
        if (empty($title)) {
            $title = $menus['dir'];
        }
        $menus['title'] = $title;

        if (!empty($id)) {
            empty($paths[$id]) && $paths[$id] = [];
            $paths[$id]['ref'] = $menus['ref'];
            $paths[$id]['title'] = $title;
        }

        if (empty($childs)) {
            return;
        }

        $subDirs = [];
        foreach ($childs as $cDir) {
            $subPath = "{$path}/{$cDir}";
            list($tDirs, $tFiles) = $this->getPluginsHelpDirsFiles($subPath);
            if (isset($tFiles['hide'])) {
                continue;
            }
            if (empty($menus['ref'])) {
                $ref = $cDir;
            } else {
                $ref = $menus['ref'] . "/" .$cDir;
            }
            $id = substr(md5($ref), 12, 8);
            $childInfo = [
                'dir' => $cDir,
                'ref' => $ref,
                'id' => $id
            ];
            empty($paths[$id]) && $paths[$id] = [];
            $paths[$id]['dirs'] = $tDirs;
            $paths[$id]['files'] = $tFiles;
            if (empty($tFiles['sort'])) {
                $sort = 10000;
            } else {
                $sort = self::xFile()->read($subPath . "/" . $tFiles['sort']);
                empty($sort) && $sort != 0 && $sort = 10000;
            }

            empty($subDirs[$sort]) && $subDirs[$sort] = [];
            $subDirs[$sort][] = $childInfo;
        }

        ksort($subDirs);
        if (!empty($subDirs)) {
            $menus['children'] = [];
            foreach ($subDirs as $sdir) {
                foreach ($sdir as $d) {
                    $menus['children'][] = $d;
                }
            }
        }

        if (!empty($menus['children'])) {
            foreach ($menus['children'] as $key=>$val) {
                $childrenPath = $path . "/" . $val['dir'];
                $indexs[] = $val['id'];
                $this->getPluginsHelpMenus($childrenPath, $menus['children'][$key], $paths, $indexs);
            }
        }
    }

    /**
     * 获取路径中的文件或文件夹信息
     * @param string $path
     * @return array
     */
    private function getPluginsHelpDirsFiles($path = '')
    {
        $info = self::xFile()->getDirsFiles($path);
        $dirs = $info['dirs'];
        !isset($dirs) && $dirs = [];
        $files = [];
        $filesSrc = $info['files'];
        if (!empty($filesSrc)) {
            foreach ($filesSrc as $fs) {
                $files[strtolower($fs)] = $fs;
            }
        }
        return [$dirs, $files];
    }

    /**
     * 获取执行函数数据
     * @param string $callStr
     * @param null $plu
     * @return string
     */
    private static function getMDCall($callStr = '', $plu = null)
    {
        $callStr = trim($callStr);
        if (empty($callStr)) {
            return $callStr;
        }

        $callArr = explode(",", $callStr);
        foreach ($callArr as $caKey => $caVal) {
            $callArr[$caKey] = trim($caVal);
        }

        $ret = $plu->call(...$callArr);

        if (is_array($ret)) {
            return json_encode($ret, true);
        }

        return $ret;
    }

    /**
     * 获取md文件中的函数调用
     * @param string $content
     * @param string $helpPluPath
     * @return string
     */
    private static function getMDContent($content = '', $helpPluPath = '')
    {
        $contentArr = explode("\${{", $content);
        if (count($contentArr) <= 1) {
            return $content;
        }

        $plu = plu($helpPluPath);
        foreach ($contentArr as $caKey => $caVal) {
            if ($caKey <= 0) {
                continue;
            }
            
            $pos = strpos($caVal, "}}");
            if ($pos === false) {
                continue;
            }
            $callStr = substr($caVal, 0, $pos);
            $caVal = substr($caVal, $pos + 2);
            $contentArr[$caKey] = self::getMDCall($callStr, $plu) . $caVal;
        }

        return implode("", $contentArr);
    }

    /**
     * 获取插件全局配置
     * @param $srcConfig
     * @param $newConfig
     * @return array
     */
    public static function getPluginsConfig($srcConfig, $newConfig)
    {
        if (is_function($newConfig)) {
            $newConfig = $newConfig();
        }

        $ret = [];
        if ($newConfig === false) {
            return $ret;
        }

        if (is_function($srcConfig)) {
            $srcConfig = $srcConfig();
        }

        if (is_array($srcConfig)) {
            $ret = $srcConfig;
        }

        if (empty($newConfig) || !is_array($newConfig)) {
            return $ret;
        }

        foreach ($newConfig as $key => $val) {
            if ($val === false) {
                if (isset($ret[$key])) {
                    unset($ret[$key]);
                }
                continue;
            }

            if (is_string($val)) {
                $val = trim($val);
                if (empty($val)) {
                    continue;
                }

                if (empty($ret[$key]) || is_string($ret[$key])) {
                    $ret[$key] = $val;
                    continue;
                }
            }

            if (empty($val)) {
                continue;
            }

            if (!is_array($val)) {
                $ret[$key] = $val;
                continue;
            }

            if (!is_array($ret[$key])) {
                $ret[$key] = [];
            }

            foreach ($val as $k => $v) {
                if ($v === false) {
                    unset($ret[$key][$k]);
                } else {
                    $ret[$key][$k] = $v;
                }
            }
        }
        return $ret;
    }

    /**
     * 运行插件
     * @param $config
     */
    public static function runPluginsConfig($config)
    {
        if (empty($config) || !is_array($config)) {
            return;
        }

        $plus = [];
        // 插件函数调用
        $call = $config['call'];
        if (!empty($call) && is_array($call)) {
            foreach ($call as $key => $val) {
                if (is_string($val)) {
                    $val = trim($val);
                    if (empty($val)) {
                        $val = 'index';
                    }
                    $val = [$val];
                } elseif (is_array($val)) {
                    $newVal = [];
                    foreach ($val as $v) {
                        if (is_string($v)) {
                            $v = trim($v);
                            if (empty($v)) {
                                $v = 'index';
                            }
                            $newVal[] = $v;
                        }
                    }
                    $val = array_unique($newVal);
                    if (empty($val)) {
                        continue;
                    }
                } else {
                    continue;
                }
                $plu = $plus[$key];
                if (empty($plu)){
                    $plu = plu($key);
                    $plus[$key] = $plu;
                }
                foreach ($val as $v) {
                    $plu->call($v);
                }
            }
        }

        $static = [];
        if (!empty($config['css'])) {
            $static['css'] = $config['css'];
        }
        if (!empty($config['js'])) {
            $static['js'] = $config['js'];
        }
        if (!empty($static)) {
            foreach ($static as $key => $val) {
                if (is_string($val)) {
                    $val = [$val];
                }
                foreach ($val as $k => $v) {
                    if (!is_string($k)) {
                        $k = '';
                    }
                    $plu = $plus[$k];
                    if (empty($plu)){
                        $plu = plu($k);
                        $plus[$k] = $plu;
                    }
                    $plu->$key($v);
                }
            }
        }
    }

    /**
     * 动态设置JS和CSS文件
     * @param $pluDir
     * @param $dir
     * @param $type
     * @param bool $isRun
     */
    public static function setPluginsStaticPaths($pluDir, $dir, $type, $isRun = false)
    {
        $pos = strpos($dir, ".");
        $rootPath = PluginInit::getPluginDir($pluDir);

        $browser = self::$browser;
        $filePath = "{$rootPath}/{$dir}/view.{$type}";
        $fileBrowserPath = "{$rootPath}/{$dir}/view.{$type}";
        if (!is_file($filePath) && !is_file($fileBrowserPath)) {
            return;
        }

        if (!isset(TphpConfig::$obStart)) {
            TphpConfig::$obStart = [];
        }

        if (!isset(TphpConfig::$obStart['imports'])) {
            TphpConfig::$obStart['imports'] = [];
        }

        if (!isset(TphpConfig::$obStart['statics'])) {
            TphpConfig::$obStart['statics'] = [];
        }
        self::__getPluginsScssSjs($rootPath, $dir, $type, $pluDir, TphpConfig::$obStart['imports'], TphpConfig::$obStart['statics'], $isRun);
    }

    /**
     * 插件帮助文档 (动态页面调用)
     * @param array $data
     * @param string $type
     */
    public function pluginsHelpCall($data = [], $type = '', $pluCall = [])
    {
        $isCall = false;
        $dc = TphpConfig::$domain;
        $dcCall = $dc['call'];
        if (is_function($dcCall)) {
            $dcCall($data, $type);
            $isCall = true;
        } else {
            list($pluMethod, $pluDir) = $pluCall;
            if (is_string($dcCall)) {
                $dcCall = trim($dcCall);
                if (!empty($dcCall)) {
                    $pluDir = $dcCall;
                }
            }
            $pluDir = trim($pluDir);
            if(!empty($pluDir)) {
                plu($pluDir)->call($pluMethod, $data, $type);
                $isCall = true;
            }
        }
        if ($isCall) {
            self::loadStatic();
        }
    }

    /**
     * 插件帮助文档
     * @param array $dirs
     */
    public function pluginsHelp($dirs = [], $isRoot = false)
    {
        ob_start('\Tphp\Domains\DomainsController::runObStartHtml');
        $dc = TphpConfig::$domain;
        if (count($dirs) < 2) {
            Run::abort(404);
        }

        $top = $dirs[0];
        $dir = $dirs[1];
        unset($dirs[0]);
        unset($dirs[1]);

        $helpPluPath = "{$top}/{$dir}";

        if ($isRoot){
            $urlRoot = '/';
        } else {
            $urlRoot = "/help/plugins/{$helpPluPath}/";
        }

        $sysPluginsPath = PluginInit::getPluginDir($helpPluPath, 'help');

        if (!is_dir($sysPluginsPath)) {
            if ($dc['404']) {
                Run::abort(404);
            }
            __exit("Plugins Help： {$helpPluPath} is not setting");
        }

        $menus = [];
        $paths = [];
        $indexs = [];
        $this->getPluginsHelpMenus($sysPluginsPath, $menus, $paths, $indexs);
        if (empty($menus['title'])) {
            $menus['title'] = '说明文档';
        }
        $pathsDefault = $paths['default'];
        unset($paths['default']);
        $rootFiles = $pathsDefault['files'];

        $id = $dirs[2];
        $idDefault = '';
        if (is_array($menus['children'])) {
            foreach ($menus['children'] as $key=>$p){
                $idDefault = $p['id'];
                break;
            }
        }
        if (empty($id) || count($dirs) > 1) {
            if (empty($idDefault)) {
                Run::abort(404);
            }
            $id = $idDefault;
        }

        $callStr = '';
        if (!empty($rootFiles['call'])) {
            $callStr = trim(self::xFile()->read($sysPluginsPath . "/" . $rootFiles['call']));
        }
        $pluCall = [$callStr, $helpPluPath];

        if ($id == 'search') {
            $keyword = trim($_GET['keyword']);
            $markdown = import('MarkDown');
            if (empty($keyword)) {
                EXITJSON([]);
            }
            $ret = [];
            $keyword = strtolower($keyword);
            foreach ($paths as $pathKey => $pathInfo) {
                $tPath = "{$sysPluginsPath}/{$pathInfo['ref']}/";
                if (empty($pathInfo['files']['readme.md'])) {
                    continue;
                }
                $c = self::getMDContent(self::xFile()->read("{$tPath}" . $pathInfo['files']['readme.md']), $helpPluPath);
                if (empty($c) || !$markdown->isSearch($c, $keyword)) {
                    continue;
                }
                $ret[] = [
                    "id" => $pathKey,
                    "title" => $pathInfo['title'],
                    "path" => $pathKey,
                ];
            }
            $this->pluginsHelpCall($keyword, $id, $pluCall);
            EXITJSON(1, 'ok', $ret);
        } elseif ($id == 'upload') {
            if (TphpConfig::$domain['debug'] !== true) {
                EXITJSON(0, '需开启调试模式');
            }

            $path = $_POST['path'];

            if (empty($path) || !is_string($path)) {
                EXITJSON(0, '保存路径未设置');
            }

            if ($path !== strtolower($path)) {
                EXITJSON(0, '保存路径不能包含大写字母');
            }

            $file = $_FILES['file'];
            if (empty($file)) {
                EXITJSON(0, '未找到上传文件');
            }

            $path = str_replace("\\", "/", $path);
            if ($path[0] . $path[1] == '//') {
                EXITJSON(0, '保存路径不正确');
            }

            $path = trim($path, " \\/");
            if (empty($path)) {
                EXITJSON(0, '保存路径未设置');
            }

            if (strpos($path, " ") !== false) {
                EXITJSON(0, '保存路径不能有空格');
            }

            if (strpos($path, ":") !== false || strpos($path, "..") !== false) {
                EXITJSON(0, '保存路径不正确');
            }

            $pos = strrpos($path, '.');
            if ($pos === false) {
                EXITJSON(0, '未知类型文件');
            }

            $ext = strtolower(substr($path, $pos + 1));
            if (!in_array($ext, ['jpg', 'jpeg', 'gif', 'png', 'ico', 'bmp'])) {
                EXITJSON(0, '非图片格式文件');
            }

            $filename = strtolower($file['name']);
            $pos = strrpos($filename, '.');
            $tmp_name = $file["tmp_name"];
            $tmp_ext = substr($filename, $pos + 1);

            if ($pos === false || $tmp_ext !== $ext) {
                if (in_array($tmp_ext, ['ico', 'bmp'])) {
                    EXITJSON(0, "无法转换{$tmp_ext}文件");
                }

                // 'jpg', 'jpeg', 'gif', 'png' 文件相互转换
                $finfo = finfo_open(FILEINFO_MIME);
                $finfoType = finfo_file($finfo, $tmp_name);
                finfo_close($finfo);
                $fPos = strpos($finfoType, ";");
                if ($fPos >= 0) {
                    $finfoType = trim(substr($finfoType, 0, $fPos));
                }

                $types = [
                    'jpg' => 'jpeg',
                    'jpeg' => 'jpeg',
                    'gif' => 'gif',
                    'png' => 'png'
                ];
                if (strpos(trim($finfoType), "image/") === 0) {
                    $img = \imagecreatefromstring(file_get_contents($tmp_name));
                } else {
                    EXITJSON(0, '未知类型文件');
                }
                $func = "\image". $types[$ext];
                $func($img, $tmp_name);
            }

            $pluDir = '';
            $pathArr = explode("/", $path);
            if (count($pathArr) > 4) {
                if ("{$pathArr[0]}/{$pathArr[1]}" === 'static/plugins') {
                    if (!empty($pathArr[2]) && !empty($pathArr[3])) {
                        $pluDir = "{$pathArr[2]}/{$pathArr[3]}";
                        unset($pathArr[0]);
                        unset($pathArr[1]);
                        unset($pathArr[2]);
                        unset($pathArr[3]);
                    }
                }
            }

            if (empty($pluDir)) {
                $path = "public/{$path}";
            } else {
                $pluRoot = Register::getHtmlPath() . "plugins/{$pluDir}";
                if (!is_dir(base_path($pluRoot))) {
                    EXITJSON(0, '插件目录不存在');
                }

                $path = "{$pluRoot}/static/" . implode("/", $pathArr);
            }

            $basePath = base_path() . "/";
            $pos = strrpos($path, "/");
            if ($pos !== false) {
                $rPath = substr($path, 0, $pos);
                $pathDir = $basePath . $rPath;
                if (!is_dir($pathDir)) {
                    self::xFile()->mkDir($pathDir . "/");
                    if (!is_dir($pathDir)) {
                        EXITJSON(0, "无权限创建文件夹: {$rPath}");
                    }
                }
            }

            try {
                if (!move_uploaded_file($tmp_name, $basePath . $path)) {
                    EXITJSON(0, "文件上传失败");
                }
            } catch (\Exception $e) {
                EXITJSON(0, $e->getMessage());
            }

            EXITJSON(1, '上传成功');
        }

        $isTop = count($dirs) <= 0;
        $pathInfo = $paths[$id];
        if (empty($pathInfo)) {
            $id = $idDefault;
            $pathInfo = $paths[$id];
            $isTop = true;
        }
        $ref = $pathInfo['ref'];
        $title = empty($dirs[2]) ? '' : $pathInfo['title'];
        $refPath = "{$sysPluginsPath}/{$ref}/";
        if (empty($pathInfo['files']['readme.md'])) {
            $content = '';
        } else {
            $refFile = "{$refPath}" . $pathInfo['files']['readme.md'];
            $content = self::getMDContent(self::xFile()->read($refFile), $helpPluPath);
            $content = $this->getReplaceStatic($content, $this->getStaticPath($helpPluPath));
        }

        $callData = [
            "id" => $id,
            'title' => $title,
            'content' => $content,
            'path' => $paths[$id]['ref']
        ];

        if ($_GET['type'] == 'json') {
            $this->pluginsHelpCall($callData, 'json', $pluCall);
            EXITJSON(1, 'ok', $content);
        }

        if (empty($rootFiles['title'])) {
            $mainTitle = "";
        } else {
            $mainTitle = self::xFile()->read($sysPluginsPath . "/" . $rootFiles['title']);
        }

        $keywords = '';
        if (!empty($pathInfo['files']['keywords'])) {
            $keywords = self::xFile()->read($refPath . $pathInfo['files']['keywords']);
        }
        if (empty($keywords) && !empty($rootFiles['keywords'])) {
            $keywords = self::xFile()->read($sysPluginsPath . "/" . $rootFiles['keywords']);
        }

        $description = '';
        if (!empty($pathInfo['files']['description'])) {
            $description = self::xFile()->read($refPath . $pathInfo['files']['description']);
        }
        if (empty($description) && !empty($rootFiles['description'])) {
            $description = self::xFile()->read($sysPluginsPath . "/" . $rootFiles['description']);
        }

        $insert = [
            'footer', // 页脚设置，如果为空默认设置TPHP信息
            'head', // Head标签中代码
            'body' // Body标签中代码
        ];
        $footer = '';
        $head = '';
        $body = '';
        foreach ($insert as $ins) {
            $insertValue = $dc[$ins];
            if (!empty($insertValue) && is_string($insertValue)) {
                $insertValue = trim($insertValue);
            } else {
                $insertValue = '';
            }
            if (!empty($rootFiles[$ins])) {
                $insertValue = trim(self::xFile()->read($sysPluginsPath . "/" . $rootFiles[$ins]));
            }
            switch ($ins) {
                case 'footer':
                    $footer = $insertValue;
                    if (empty($footer)) {
                        $footer = config('default.help.footer');
                    }
                    break;
                case 'head':
                    $head = $insertValue;
                    break;
                case 'body':
                    $body = $insertValue;
                    break;
            }
        }

        $seoList = [];
        $httpList = explode("http://", $content);
        if (count($httpList) > 0) {
            unset($httpList[0]);
        }
        foreach ($httpList as $hl) {
            $seoList[] = "http://" . $hl;
        }

        $httpsList = explode("https://", $content);
        if (count($httpsList) > 0) {
            unset($httpsList[0]);
        }

        foreach ($httpsList as $hl) {
            $seoList[] = "https://" . $hl;
        }

        $seoTypes = [
            " " => true,
            "\n" => true,
            "\t" => true,
            ")" => true,
            "(" => true,
            "[" => true,
            "]" => true
        ];

        foreach ($seoList as $key => $val) {
            $valLen = strlen($val);
            $newVal = "";
            for ($i = 0; $i < $valLen; $i ++) {
                $chr = $val[$i];
                if ($seoTypes[$chr]) {
                    break;
                }
                $newVal .= $chr;
            }
            $seoList[$key] = $newVal;
        }

        $seoList = array_unique($seoList);

        $urlRootBase = rtrim($urlRoot, "\\/");
        empty($urlRootBase) && $urlRootBase = $urlRoot;
        $content = str_replace("{{", "&#123;&#123;", $content);
        $content = str_replace("}}", "&#126;&#126;", $content);
        $helpViewData = [
            "id" => $id,
            'config' => $menus['children'],
            'mainTitle' => $mainTitle,
            'title' => $title,
            'paths' => $paths,
            'content' => $content,
            'keywords' => $keywords,
            'description' => $description,
            'urlRoot' => $urlRoot,
            'urlRootBase' => $urlRootBase,
            'indexs' => $indexs,
            'seoList' => $seoList,
            'isTop' => $isTop,
            'footer' => $footer,
            'head' => $head,
            'body' => $body
        ];

        $this->pluginsHelpCall($callData, 'html', $pluCall);
        $retView = plu('sys.default')->view('help', $helpViewData);
        self::loadStatic();

        return $retView;
    }
}
