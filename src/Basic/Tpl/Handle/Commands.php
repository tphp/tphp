<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl\Handle;

/**
 * 公共函数
 * Trait LoadStatic
 * @package Tphp\Basic\Tpl\Handle
 */
trait Commands
{
    private static $xFile;

    /**
     * 代码重构
     * @param $html
     * @return string
     */
    private static function obStart($html)
    {
        $obc = \Tphp\Config::$obStart;
        if (is_array($obc)) {
            $header = $obc['header'];
            if (is_array($header)) {
                foreach ($header as $key => $val) {
                    header("{$key}:{$val}");
                }
            }
        }
        return $html;
    }

    /**
     * 获取XFile
     * @return null
     */
    private static function xFile()
    {
        if (empty(self::$xFile)) {
            self::$xFile = import("XFile");
        }
        return self::$xFile;
    }

    /**
     * 获取静态路径
     * @param string $pluDir
     * @return string
     */
    private function getStaticPath($pluDir = '')
    {
        return "/static/plugins/{$pluDir}/";
    }

    /**
     * 模板字符转换
     * @param $codeStr 原代码
     * @param string $flag 替换标识
     * @param string $repStr 替换字符串
     * @param string $extStr 后缀替换
     * @return mixed
     */
    private function getReplaceFlag($codeStr, $flag = "", $repStr = "", $extStr = '')
    {
        $chgStr = "#_0&197~"; //替换符号，确保文件中不会使用到的字符串
        $codeStr = str_replace("$" . $flag, $chgStr, $codeStr); //当文件中存在$$codeStr字符串则输出为$codeStr
        if (!empty($extStr)) {
            $codeStr = str_replace($flag . $extStr, $flag, $codeStr);
        }
        $codeStr = str_replace($flag, $repStr, $codeStr);
        $codeStr = str_replace($chgStr, $flag, $codeStr);
        return $codeStr;
    }

    /**
     * 获取 __STATIC__ 转换
     * @param $codeStr
     * @param string $repStr
     * @return string
     */
    private function getReplaceStatic($codeStr, $repStr = "")
    {
        return $this->getReplaceFlag($codeStr, '__STATIC__', $repStr, '/');
    }

    /**
     * 模板字符转换
     * @param $filePath 文件路径
     * @param string $class 模板名
     * @return mixed|string
     */
    private function getFileTextIn($filePath, $class = "")
    {
        return $this->getReplaceFlag(trim(self::xFile()->read($filePath)), '_CLASS_', "." . $class);
    }

    /**
     * 获取智能分隔符
     * @param $dir
     * @return string
     */
    private function getRemarkFlag($dir)
    {
        $len = intval(strlen($dir) / 2);
        $int = 20 - $len;
        if ($len < 0) {
            $int = 0;
        }
        if ($int > 0) {
            $flag = str_pad("", $int, "-");
        } else {
            $flag = '';
        }
        return $flag;
    }

    /**
     * 删除软连接，兼容Windows和Linux
     * @param $path
     */
    private function unLink($path)
    {
        try {
            if (PHP_OS == 'WINNT') {
                $path = str_replace("/", "\\", $path);
                if (is_file($path)) {
                    unlink($path);
                } else {
                    @rmdir($path);
                }
            } else {
                unlink($path);
            }
        } catch (\Exception $e) {
            // Nothing TODO
        }
    }

    /**
     * 获取文件头信息
     * @param string $fileName
     * @return bool|mixed|null|string
     */
    private function getFileMime($fileName = '')
    {
        $fileExt = '';
        $pos = strrpos($fileName, ".");
        if ($pos > 0) {
            $fileExt = strtolower(substr($fileName, $pos + 1));
        }
        if (empty($fileExt) || !in_array($fileExt, ['js', 'css', 'txt', 'jpg', 'jpeg', 'gif', 'bmp', 'png', 'ttf', 'woff', 'woff2'])) {
            return null;
        }

        switch ($fileExt) {
            case 'css' :
                return 'text/css';
            case 'js' :
                return 'application/javascript';
        }

        $fInfo = finfo_open(FILEINFO_MIME);
        $mimeType = finfo_file($fInfo, $fileName);
        finfo_close($fInfo);
        $pos = strpos($mimeType, ';');
        $pos > 0 && $mimeType = substr($mimeType, 0, $pos);
        if ($mimeType !== 'text/plain') {
            return $mimeType;
        }

        return $mimeType;
    }

    /**
     * 获取相对路径
     * @param $urla
     * @param $urlb
     * @return string
     */
    private function getRelativePath($urla, $urlb)
    {
        $aDirname = trim($urla, "/");
        $bDirname = trim($urlb, "/");
        $aArr = explode("/", $aDirname);
        $bArr = explode("/", $bDirname);
        $count = 0;
        $bArrCount = count($bArr);
        $num = min(count($aArr), $bArrCount);
        for ($i = 0; $i < $num; $i++) {
            if ($aArr[$i] == $bArr[$i]) {
                unset($aArr[$i]);
                $count++;
            } else {
                break;
            }
        }
        $relativePath = str_repeat("../", $bArrCount - $count - 1) . implode("/", $aArr);
        return $relativePath;
    }

    /**
     * 获取URL
     * @param null $url
     * @return \Illuminate\Contracts\Routing\UrlGenerator|string
     */
    public static function getUrl($url = null)
    {
        if (is_null($url)) {
            return url();
        }

        $isDomain = \Tphp\Config::$domain['isdomain'];
        if (is_bool($isDomain)) {
            if ($isDomain) {
                return url($url);
            }
            return $url;
        }

        if (!is_string($url)) {
            return url($url);
        }

        $isDomain = trim($isDomain);
        $isDomain = rtrim($isDomain, "\\/");
        if (empty($url) || !is_string($url)) {
            return $isDomain;
        }

        if (strpos($url, "://") !== false) {
            return $url;
        }

        $url = ltrim($url, "\\/");
        return "{$isDomain}/{$url}";
    }

    /**
     * 判断是否是外部链接
     * @param $url
     * @return bool
     */
    public static function isUrl($url = '')
    {
        if (!is_string($url)) {
            return false;
        }
        return ($url[0] === '/' && $url[1] === '/') || strpos($url, "://") !== false;
    }

    /**
     * 获取当前页面完整路径URL
     * @return string
     */
    public static function getFullUrl($isRequest = false){
        # 解决通用问题
        $requestUri = '';
        if (isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
        } else {
            if (isset($_SERVER['argv'])) {
                $requestUri = $_SERVER['PHP_SELF'] .'?'. $_SERVER['argv'][0];
            } else if(isset($_SERVER['QUERY_STRING'])) {
                $requestUri = $_SERVER['PHP_SELF'] .'?'. $_SERVER['QUERY_STRING'];
            }
        }

        if ($isRequest) {
            return $requestUri;
        }

        $scheme = $_SERVER["HTTPS"] == "on" ? "s" : "";
        $protocol = strstr(strtolower($_SERVER["SERVER_PROTOCOL"]), "/",true) . $scheme;
        //端口还是蛮重要的，毕竟需要兼容特殊的场景
        $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
        # 获取的完整url
        $fullUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . $port . $requestUri;
        return $fullUrl;
    }
}
