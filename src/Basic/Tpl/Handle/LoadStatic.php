<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl\Handle;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Cache;
use Tphp\Basic\JSMin\JSMin;
use Tphp\Basic\Tpl\Run;
use Tphp\Basic\Tpl\Init;
use Tphp\Basic\Plugin\Init as PluginInit;
use Tphp\Register;

/**
 * 加载静态文件
 * Trait LoadStatic
 * @package Tphp\Basic\Tpl\Handle
 */
trait LoadStatic
{
    /**
     * css压缩
     * @param $buffer
     * @return mixed
     */
    private function cssZip($buffer)
    {
        $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
        $buffer = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $buffer);
        return $buffer;
    }

    private static function obTplCss()
    {
        ob_start('self::obStart');
        Run::header('Content-type:text/css');
    }

    private static function obTplJs()
    {
        ob_start('self::obStart');
        Run::header('Content-type:application/x-javascript');
    }

    /**
     * 获取 css 或 js 文件内容
     * @param $list
     * @param string $type
     * @param string $tplName
     * @param string $static
     * @return string
     */
    private function getFileText($list, $type = 'css', $tplName = "", $static = '')
    {
//        __header('Content-Type: text/html; charset=UTF-8');
//        dump($list, $tplName);

        if (empty($list)) return "";
        $str = "";
        $browser = self::$browser;

        if ($type == 'css') {
            foreach ($list as $key => $val) {
                list($val, $vType) = explode(":", $val);
                if (empty($tplName)) {
                    $class = str_replace(".", "_", $val);
                } else {
                    $class = str_replace(".", "_", $tplName);
                }
                $tStr = "";
                $class = str_replace("/", "_", $class);
                $valPath = str_replace(".", "/", $val);

                $filePaths = [];
                $filePaths[Register::getViewPath($valPath . "/{$vType}.css", false)] = [false, false];
                $filePaths[Register::getViewPath($valPath . "/{$vType}.{$browser}.css", false)] = [false, true];
                $filePaths[Register::getViewPath($valPath . "/{$vType}.scss", false)] = [true, false];
                $filePaths[Register::getViewPath($valPath . "/{$vType}.{$browser}.scss", false)] = [true, true];
                foreach ($filePaths as $fp => list($isScss, $isBrowser)) {
                    if (is_file($fp)) {
                        $fpStr = $this->getFileTextIn($fp, $class);
                        if ($isScss) {
                            $vPath = "{$val}/{$vType}";
                            if ($isBrowser) {
                                $vPath .= ".{$browser}";
                            }
                            $fpStr = \Tphp\Scss\Run::getCode($fpStr, $vPath);
                        }
                        $tStr .= $fpStr . "\n\n";
                    }
                }

                $tStr = trim($tStr);
                $tStr = $this->getReplaceStatic($tStr, $static);
                if (!empty($tStr)) {
                    if (!$this->tplCache) {
                        if (!empty($tplName)) {
                            $val = "top: {$val}";
                        }
                        $val .= ":{$vType}";
                        $vFlag = $this->getRemarkFlag($val);
                        $tStr = "/*{$vFlag} {$val} {$vFlag}*/\n\n{$tStr}\n\n";
                    }
                    $str .= $tStr;
                }
            }
        } else {
            foreach ($list as $key => $val) {
                list($val, $vType) = explode(":", $val);
                if (empty($tplName)) {
                    $class = str_replace(".", "_", $val);
                } else {
                    $class = str_replace(".", "_", $tplName);
                }
                $class = str_replace("/", "_", $class);
                $valPath = str_replace(".", "/", $val);
                $jsStr = "";

                $filePaths = [];
                $filePaths[Register::getViewPath($valPath . "/{$vType}.js", false)] = false;
                $filePaths[Register::getViewPath($valPath . "/{$vType}.{$browser}.js", false)] = true;
                foreach ($filePaths as $fp => $isBrowser) {
                    if (is_file($fp)) {
                        $jsStr .= $this->getFileTextIn($fp, $class) . "\n\n";
                    }
                }

                $jsStr = trim($jsStr, ";");
                $jsStr = $this->getReplaceStatic($jsStr, $static);
                $jsStr = trim($jsStr);
                !empty($jsStr) && $jsStr = $jsStr . ";";
                if (!empty($jsStr)) {
                    $tStr = trim($jsStr);
                    if ($this->tplCache) {
                        $tStr = rtrim($jsStr, ';') . ";";
                    } else {
                        if (!empty($tplName)) {
                            $val = "top: {$val}";
                        }
                        $val .= ":{$vType}";
                        $vFlag = $this->getRemarkFlag($val);
                        $tStr = "/*{$vFlag} {$val} {$vFlag}*/\n\n{$jsStr}\n\n";
                    }
                    $str .= $tStr;
                }
            }
        }

        $str = trim($str) . "\n\n";
        if (!empty($tplName)) {
            $str .= "\n\n";
        }
        return $str;
    }

    /**
     * CSS 生成模块
     * @return mixed|string
     */
    private function getCss($md5)
    {
        $ini = Cache::get("css_t_{$md5}");
        $thisTplArr = Cache::get("css_t_{$md5}_type");
        $thisTplName = Cache::get("{$md5}_tpl");
        if (!is_null(Register::$tplPath)) {
            $thisTplName = Register::$tplPath . "_" . $thisTplName;
        } elseif (!is_null(Register::$topPath)) {
            $thisTplName = Register::$topPath . "_" . $thisTplName;
        }
        if (empty($ini) && empty($thisTplArr)){
            return false;
        }

        $static = Cache::get("css_t_{$md5}_static");
        $codeTop = $this->getFileText($thisTplArr, 'css', $thisTplName, $static);
        $code = $codeTop . $this->getFileText($ini, 'css', null, $static);
        return $code;
    }

    /**
     * JS 生成模块
     * @return mixed|string
     */
    private function getJs($md5)
    {
        $ini = Cache::get("js_t_{$md5}");
        $thisTplArr = Cache::get("js_t_{$md5}_type");
        $thisTplName = Cache::get("{$md5}_tpl");
        if (!is_null(Register::$tplPath)) {
            $thisTplName = Register::$tplPath . "_" . $thisTplName;
        } elseif (!is_null(Register::$topPath)) {
            $thisTplName = Register::$topPath . "_" . $thisTplName;
        }
        if (empty($ini) && empty($thisTplArr)){
            return false;
        }

        $static = Cache::get("css_t_{$md5}_static");
        $codeDown = $this->getFileText($thisTplArr, 'js', $thisTplName, $static);
        $code = $this->getFileText($ini, 'js', null, $static) . $codeDown;
        return $code;
    }

    /**
     * 获取JS或CSS生成代码
     * @param $run
     * @param string $type
     * @return string
     */
    private function getRunJsCode($run, $type = 'js')
    {
        $runs = [];
        if ($type == 'js') {
            $ext = 'js';
        } else {
            $ext = 'scss';
        }
        $browser = self::$browser;
        foreach ($run as $pluDir => $dirs) {
            $sysPluginsPath = PluginInit::getPluginDir($pluDir);
            $static = $this->getStaticPath($pluDir);
            foreach ($dirs as $dir) {
                $files = [];
                $files["{$sysPluginsPath}/{$dir}/view.{$ext}"] = false;
                $files["{$sysPluginsPath}/{$dir}/view.{$browser}.{$ext}"] = true;
                foreach ($files as $file => $isBrowser) {
                    if (!is_file($file)) {
                        continue;
                    }
                    $code = trim(self::xFile()->read($file));
                    if (empty($code)) {
                        continue;
                    }
                    if ($code[0] == '/' && $code[1] == '*') {
                        $pos = strpos($code, '*/');
                        if ($pos > 0) {
                            $code = trim(substr($code, $pos + 2));
                            if (empty($code)) {
                                continue;
                            }
                        }
                    }
                    $code = $this->getReplaceStatic($code, $static);
                    if ($this->tplCache) {
                        $code = rtrim($code, ';') . ";";
                    } else {
                        $showDir = "{$pluDir}: {$dir}";
                        if ($isBrowser) {
                            $showDir .= ".{$browser}";
                        }
                        $flag = $this->getRemarkFlag($showDir);
                        $code = "/*{$flag} {$showDir} {$flag}*/\n\n{$code}";
                    }
                    $runs[] = $code;
                }
            }
        }
        return implode("\n\n", $runs);
    }

    /**
     * 动态运行CSS 生成模块
     * @return mixed|string
     */
    private function getRunCss($md5)
    {
        $runCss = Cache::get("runcss_{$md5}");
        if (empty($runCss)){
            return false;
        }

        $msgs = [];
        foreach ($runCss as $key => $val) {
            $msgs[] = "plugins/{$key}: " . implode(', ', $val);
        }
        $code = \Tphp\Scss\Run::getCode($this->getRunJsCode($runCss, 'css'), implode("\n", $msgs));
        return $code;
    }

    /**
     * 动态运行JS 生成模块
     * @return mixed|string
     */
    private function getRunJs($md5)
    {
        $runJs = Cache::get("runjs_{$md5}");
        if (empty($runJs)){
            return false;
        }
        $code = $this->getRunJsCode($runJs);
        return $code;
    }

    /**
     * 获取CSS代码
     * @return mixed|string
     */
    public function _css($md5)
    {
        $cssStr = Cache::get("css_cache_{$md5}");
        if (empty($cssStr)){
            return false;
        }
        list($cssMd5, $runCssMd5) = explode("#", $cssStr);
        $codes = [];
        if (!empty($cssMd5)) {
            $codes[] = $this->getCss($cssMd5);
        }
        if (!empty($runCssMd5)) {
            $codes[] = $this->getRunCss($runCssMd5);
        }
        if ($codes[0] === false && $codes[1] === false){
            return false;
        }

        $rets = [];
        foreach ($codes as $code) {
            if (!is_string($code)) {
                continue;
            }
            $code = trim($code);
            if (!empty($code)) {
                $rets[] = $code;
            }
        }

        if ($this->tplCache) {
            $sep = "";
        } else {
            $sep = "\n\n";
        }

        $code = implode($sep, $rets);
        return $code;
    }

    /**
     * 获取CSS代码
     * @return mixed|string
     */
    public function css()
    {
        self::obTplCss();
        $request = Request();
        $md5 = $request->md5;
        if ($this->tplCache) {
            $cacheId = "css_{$md5}";
            $codes = Cache::get($cacheId);
            if ($codes['tag']) {
                $code = $codes['code'];
            } else {
                $code = $this->_css($md5);
                if ($code === false) {
                    throw new NotFoundHttpException;
                }
                $code = $this->cssZip($code);
                Cache::put($cacheId, [
                    'tag' => true,
                    'code' => $code
                ], 60 * 60);
            }
        } else {
            $code = $this->_css($md5);
            if ($code === false) {
                throw new NotFoundHttpException;
            }
        }
        return $code;
    }

    /**
     * 获取JS代码
     * @return mixed|string
     */
    public function _js($md5)
    {
        $jsStr = Cache::get("js_cache_{$md5}");
        if (empty($jsStr)){
            return false;
        }
        list($jsMd5, $runJsMd5) = explode("#", $jsStr);
        $codes = [];
        if (!empty($jsMd5)) {
            $codes[] = $this->getJs($jsMd5);
        }
        if (!empty($runJsMd5)) {
            $codes[] = $this->getRunJs($runJsMd5);
        }
        if ($codes[0] === false && $codes[1] === false){
            return false;
        }

        $rets = [];
        foreach ($codes as $code) {
            if (!is_string($code)) {
                continue;
            }
            $code = trim($code);
            if (!empty($code)) {
                $rets[] = $code;
            }
        }

        if ($this->tplCache) {
            $sep = ";";
        } else {
            $sep = "\n\n";
        }

        $code = implode($sep, $rets);
        return $code;
    }

    /**
     * 获取JS代码
     * @return mixed|string
     */
    public function js()
    {
        self::obTplJs();
        $request = Request();
        $md5 = $request->md5;
        if ($this->tplCache) {
            $cacheId = "js_{$md5}";
            $codes = Cache::get($cacheId);
            if ($codes['tag']) {
                $code = $codes['code'];
            } else {
                $code = $this->_js($md5);
                if ($code === false) {
                    throw new NotFoundHttpException;
                }
                $code = JSMin::minify($code);
                Cache::put($cacheId, [
                    'tag' => true,
                    'code' => $code
                ], 60 * 60);
            }
        } else {
            $code = $this->_js($md5);
            if ($code === false) {
                throw new NotFoundHttpException;
            }
        }
        return $code;
    }

    /**
     * 载入JS和CSS文件
     */
    public static function loadStatic()
    {
        $static = get_ob_start_value('static');
        if (empty($static)) {
            $static = [];
        }

        $tpl = \Tphp\Config::$tpl;
        if (!empty($tpl)) {
            // 必须先运行获取配置
            $css = $tpl->getCss();
            $js = $tpl->getJs();

            if (!empty($css)) {
                $static['css_md5'] = $css;
            }
            if (!empty($js)) {
                $static['js_md5'] = $js;
            }
        }
        if (!empty(Init::$css)) {
            $static['css'] = Init::$css;
        }
        if (!empty(Init::$js)) {
            $static['js'] = Init::$js;
        }
        if (!empty($static)) {
            set_ob_start_value($static, 'static', true);
        }
        \Tphp\Domains\DomainsController::runObStart();
    }

    /**
     * 全局插件软连接
     * @param string $top
     * @param string $dir
     * @param string $fileName
     */
    public function pluginsStatic($top = '', $dir = '', $fileName = '')
    {
        if (empty($top) || empty($dir)) {
            Run::abort(404);
        }
        $sysPluginsPath = PluginInit::getPluginDir("{$top}/{$dir}", 'static');
        $topPath = public_path($this->getStaticPath($top));
        $linkFile = $topPath . $dir;
        $xFile = import('XFile');
        $readlinkPath = '';
        try {
            $readlinkPath = readlink($linkFile);
        } catch (\Exception $e) {
            // TODO
        }
        if (!is_dir($sysPluginsPath)) {
            if (!empty($readlinkPath)) {
                $this->unLink($linkFile);
            }
            Run::abort(404);
        }

        $gitignore = public_path('/static/plugins/.gitignore');
        if (!is_file($gitignore)) {
            $xFile->write($gitignore, "*\n!.gitignore");
            if (!is_file($gitignore)) {
                Run::abort(501, '无权限创建文件');
            }
        }
        if (!is_dir($topPath)) {
            $xFile->mkDir($topPath);
            if (!is_dir($topPath)) {
                Run::abort(501, '无权限创建文件夹');
            }
        }
        if (is_file($linkFile)) {
            $xFile->delete($linkFile);
        }
        if (is_dir($sysPluginsPath)) {
            if (PHP_OS == 'WINNT') {
                // Windows系统必须转化为反斜杠，否则有可能目录访问出错
                $sysPluginsPath = str_replace("/", "\\", $sysPluginsPath);
                $fileName = str_replace("/", "\\", $fileName);
                $linkFile = str_replace("/", "\\", $linkFile);
                $linkFile = str_replace("\\\\", "\\", $linkFile);
                $pathStep = '\\';
            } else {
                $pathStep = '/';
            }
            $relativePath = $this->getRelativePath($sysPluginsPath, $linkFile);
            try {
                if (empty($readlinkPath)) {
                    symlink($relativePath, $linkFile);
                } elseif ($relativePath != $readlinkPath) {
                    $this->unLink($linkFile);
                    symlink($relativePath, $linkFile);
                }
            } catch (\Exception $e) {
                // Nothing TODO
            }
            $fullName = $sysPluginsPath . $pathStep . $fileName;
            if (!is_file($fullName)) {
                $sText = $this->pluginsScssSjs($fileName, $pathStep, "{$top}/{$dir}");
                if ($sText !== null) {
                    __exit($sText);
                }
                Run::abort(404);
            }
            $mimeType = $this->getFileMime($fullName);
            if (empty($mimeType)) {
                Run::abort(404);
            } else {
                Run::header("Content-Type: {$mimeType}");
                Run::runHeader();
                $fileSize = filesize($fullName);
                $handler = fopen($fullName, "r");
                $byteStr = fread($handler, $fileSize);
                fclose($handler);
                __exit($byteStr);
            }
        } else {
            Run::abort(404);
        }
    }

    /**
     * Storage文件夹软连接
     * @param array $dirs
     */
    public function publicStorage($dirs = [])
    {
        if (empty($dirs)) {
            Run::abort(404);
        }
        $storagePath = storage_path('app/public');
        $linkFile = public_path('storage');

        $xFile = import('XFile');
        $readlinkPath = '';
        try {
            $readlinkPath = readlink($linkFile);
        } catch (\Exception $e) {
            // TODO
        }
        if (!is_dir($storagePath)) {
            if (!empty($readlinkPath)) {
                $this->unLink($linkFile);
            }
            Run::abort(404);
        }

        if (is_file($linkFile)) {
            $xFile->delete($linkFile);
        }


        if (PHP_OS == 'WINNT') {
            // Windows系统必须转化为反斜杠，否则有可能目录访问出错
            $storagePath = str_replace("/", "\\", $storagePath);
            $linkFile = str_replace("/", "\\", $linkFile);
            $linkFile = str_replace("\\\\", "\\", $linkFile);
            $pathStep = '\\';
        } else {
            $pathStep = '/';
        }

        try {
            if (empty($readlinkPath)) {
                symlink($storagePath, $linkFile);
            } elseif ($storagePath != $readlinkPath) {
                $this->unLink($linkFile);
                symlink($storagePath, $linkFile);
            }
        } catch (\Exception $e) {
            // Nothing TODO
        }

        $fullName = $storagePath . $pathStep . implode($pathStep, $dirs);
        if (!is_file($fullName)) {
            Run::abort(404);
        }
        $mimeType = $this->getFileMime($fullName);
        if (empty($mimeType)) {
            Run::abort(404);
        } else {
            Run::header("Content-Type: {$mimeType}");
            Run::runHeader();
            $fileSize = filesize($fullName);
            $handler = fopen($fullName, "r");
            $byteStr = fread($handler, $fileSize);
            fclose($handler);
            __exit($byteStr);
        }
    }

    /**
     * 图标设置
     */
    public function ico()
    {
        ob_start('self::obStart');
        $iconPath = \Tphp\Config::$domain['icon'];
        $isIcon = false;
        $iconFile = "";
        $tphpHtmlPath = Register::getHtmlPath();
        $basePath = rtrim(base_path(), "\\/") . "/";
        if (!empty($iconPath)) {
            $pos = strrpos($iconPath, ".");
            $ext = "";
            if ($pos > 0) {
                $ext = strtolower(substr($iconPath, $pos + 1));
            }
            if (in_array($ext, ['png', 'jpg', 'png', 'ico'])) {
                $pathArr = explode("/", $iconPath);
                $pathArrCot = count($pathArr);
                $isOk = true;
                $isPlugins = false;
                // 如果是插件路径
                if ($pathArrCot >= 2 && $pathArr[0] == 'static' && $pathArr[1] == 'plugins') {
                    if ($pathArrCot <= 4) {
                        $isOk = false;
                    } else {
                        $pluDir = "{$pathArr[2]}/{$pathArr[3]}";
                        unset($pathArr[0]);
                        unset($pathArr[1]);
                        unset($pathArr[2]);
                        unset($pathArr[3]);
                        $path_str = implode("/", $pathArr);
                        $iconPath =  "{$tphpHtmlPath}plugins/{$pluDir}/static/{$path_str}";
                        $isPlugins = true;
                    }
                }
                if ($isOk) {
                    if ($isPlugins) {
                        $iconFile = $basePath . "/" .$iconPath;
                    } else {
                        $iconFile = rtrim(public_path(), '/\\') . "/" .$iconPath;
                    }
                    if (is_file($iconFile)) {
                        $isIcon = true;
                    }
                }
            }
        }

        if (!$isIcon) {
            $ext = 'ico';
            $iconPaths = [
                $tphpHtmlPath . \Tphp\Config::$domain['tpl'] . "/favicon.ico",
                $tphpHtmlPath . "favicon.ico"
            ];
            foreach ($iconPaths as $iconPath) {
                $iconFile = $basePath . $iconPath;
                if (is_file($iconFile)) {
                    $isIcon = true;
                    break;
                }
            }
        }
        if (!$isIcon) {
            return;
        }
        if ($ext === 'ico') {
            $ext = 'x-icon';
        } elseif ($ext == 'jpg') {
            $ext = 'jpeg';
        }
        Run::header('Content-type: image/' . $ext);
        Run::runHeader();
        readfile($iconFile);
        __exit();
    }
}
