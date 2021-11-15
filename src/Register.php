<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp;

class Register
{
    // 主插件路径
    public static $mainPath = '';

    // 主接页面口路径
    public static $mainWebPath = null;

    // 页面顶级路径
    public static $topPath = null;

    // 模板顶级路径
    public static $tplPath = null;

    // 视图注册路径
    public static $viewPaths = [];

    // 是否已经执行打印
    public static $isDump = false;

    // vendor 根路径
    private static $vendorRoot = '';

    // 项目开发路径
    private static $htmlPath = null;

    // 项目开发真实路径
    private static $htmlRealPath = null;

    /**
     * 获取vendor路径
     * @return string
     */
    private static function getVendorRoot()
    {
        if (!empty(self::$vendorRoot)) {
            return self::$vendorRoot;
        }
        self::$vendorRoot = str_replace("\\", "/", dirname(dirname(dirname(__DIR__))));
        return self::$vendorRoot;
    }

    /**
     * 插件路径注册
     * @param String $vendorPath Composer 依赖路径
     * @param bool $tplPath 主路径设置，默认不设置，指向插件中的html目录
     */
    public static function vendor(String $vendorPath = '', $tplPath = false)
    {
        if (!is_string($vendorPath)) {
            return;
        }

        $vendorPath = str_replace("\\", "/", $vendorPath);
        $vRoot = self::getVendorRoot() . "/";
        $vRootLen = strlen($vRoot);

        // 必须在vendor文件下
        if (strlen($vendorPath) <= $vRootLen) {
            return;
        }

        $vendorRoot = substr($vendorPath, 0, $vRootLen);
        if ($vendorRoot !== $vRoot) {
            return;
        }

        $vendorInner = str_replace("\\", "/", substr($vendorPath, $vRootLen));
        $viArr = explode("/", $vendorInner);
        $viArrLen = count($viArr);
        if ($viArrLen < 2) {
            return;
        }

        $viTop = $viArr[0];
        $viSub = $viArr[1];
        $viewPath = "{$vRoot}{$viTop}/{$viSub}";

        if (!is_dir($viewPath)) {
            return;
        }

        self::$viewPaths[] = $viewPath;

        if (!is_string($tplPath)) {
            return;
        }

        $tplPath = str_replace("\\", ".", $tplPath);
        $tplPath = str_replace("/", ".", $tplPath);
        $tplPath = trim($tplPath, " .");

        self::$mainPath = $tplPath;
    }

    /**
     * 获取视图路径文件或文件夹
     * @param string $relationPath
     * @param bool $isDir 是否是文件夹
     * @return string
     */
    public static function getViewPath($relationPath = '', $isDir = true)
    {
        $retPath = base_path(self::getHtmlPath() . $relationPath);

        if ($isDir) {
            if (is_dir($retPath)) {
                return $retPath;
            }

            foreach (self::$viewPaths as $vp) {
                $retPath = "{$vp}/html/{$relationPath}";
                if (is_dir($retPath)) {
                    break;
                }
            }
            return $retPath;
        }

        if (is_file($retPath)) {
            return $retPath;
        }

        foreach (self::$viewPaths as $vp) {
            $retPath = "{$vp}/html/{$relationPath}";
            if (is_file($retPath)) {
                break;
            }
        }

        return $retPath;
    }

    /**
     * 获取项目开发路径
     * @return null|string
     */
    private static function __getHtmlPath()
    {
        if (!is_null(self::$htmlPath)) {
            return self::$htmlPath;
        }

        // 默认路径 /html
        $htmlPath = env('TPHP_PATH', 'html/');
        if (!is_string($htmlPath)) {
            $htmlPath = '';
        }
        $htmlPath = str_replace("\\", "/", $htmlPath);
        $htmlPath = trim($htmlPath, "/") . "/";

        self::$htmlPath = $htmlPath;

        return self::$htmlPath;
    }

    /**
     * 获取项目开发路径
     * @param bool $isRealPath 是否获取真实路径
     * @return null|string
     */
    public static function getHtmlPath($isRealPath = false)
    {
        if (!$isRealPath) {
            return self::__getHtmlPath();
        }

        if (!is_null(self::$htmlRealPath)) {
            return self::$htmlRealPath;
        }

        self::$htmlRealPath = realpath(base_path(trim(self::__getHtmlPath(), "/")));

        return self::$htmlRealPath;
    }

    /**
     * 获取顶部路径
     * @param string $addStr
     * @return string
     */
    public static function getTopPath($addStr = '')
    {
        if (is_null(self::$topPath)) {
            return '';
        }

        $topPath = trim(self::$topPath, " \\/");
        if ($topPath == '') {
            return $topPath;
        }

        return $topPath . $addStr;
    }
}
