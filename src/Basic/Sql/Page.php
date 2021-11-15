<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Sql;

/**
 * 分页生成页面
 * Class Page
 * @package Tphp\Basic
 */
class Page
{
    public static $pages;
    private static $typesDef = 'default';

    /**
     * 设置URL保留参数
     * @param $pages
     * @param array $saveArgs 保留参数
     * @param string $fragment 锚链接标记
     */
    private function setPageArgs($pages, $saveArgs = [], $fragment = '')
    {
        if (!empty($saveArgs)) {
            $appends = [];
            if (is_array($saveArgs)) {
                foreach ($saveArgs as $val) {
                    $val = trim($val);
                    !empty($val) && !empty($_GET[$val]) && $appends[$val] = $_GET[$val];
                }
            } else {
                $saveArgs = trim($saveArgs);
                !empty($saveArgs) && !empty($_GET[$saveArgs]) && $appends[$saveArgs] = $_GET[$saveArgs];
            }
            !empty($appends) && $pages->appends($appends);
        }

        if (!empty($fragment) && (is_string($fragment) || is_numeric($fragment))) {
            $fragment = trim($fragment);
            !empty($fragment) && $pages->fragment($fragment);
        }
    }

    /**
     * 获取模板和数据端组合
     * @param $typeName 分页模板路径
     * @param $pluPath 模板路径
     * @return array
     */
    private function getTypeInfo($typeName, $pluPath)
    {
        $viewPath = "plugins/{$pluPath}/page/{$typeName}";
        if (!view()->exists("{$viewPath}.tpl")) {
            $viewPath = "page/{$typeName}";
            if (!view()->exists("{$viewPath}.tpl")) {
                return null;
            }
        }
        return [$viewPath, $pluPath];
    }

    /**
     * 输出分页HTML代码
     * @param int $type 分页类型
     * @param array $showargs 保留参数
     * @param string $fragment 锚链接标记
     * @param int $onEachSide 分页中间显示条数，系统默认为3条，当值小于或等于0时使用系统默认值
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed|string
     */
    public function page($type = null, $saveArgs = [], $fragment = '', $onEachSide = 0)
    {
        if (empty(self::$pages)) return '';
        $pages = clone self::$pages;
        if (empty($pages)) return '';

        $domainPath = \Tphp\Config::$domainPath;
        $pluDirs = [];
        if (!empty($domainPath->basePluPath)) {
            $pluDirs[] = $domainPath->basePluPath;
        }
        if (!empty($domainPath->plu) && !empty($domainPath->plu->dir)) {
            $pluDirs[] = $domainPath->plu->dir;
        }
        $pluDirs = array_unique($pluDirs);
        if (empty($pluDirs)) {
            $pluDirs[] = '';
        }

        //设置URL保留参数
        empty($saveArgs) && $saveArgs = [];
        !in_array("psize", $saveArgs) && $saveArgs[] = "psize";
        $this->setPageArgs($pages, $saveArgs, $fragment);

        if (is_string($type) || is_numeric($type)) {
            $typeName = strtolower(trim($type));
            $className = $typeName;
        } elseif (is_array($type)) {
            $typeName = strtolower(trim($type[0]));
            $className = strtolower(trim($type[1]));
            if (empty($className)) {
                $className = $typeName;
            }
        } else {
            return '';
        }

        $typeName = trim($typeName, " \\/.");

        if (empty($typeName)) {
            $typeName = self::$typesDef;
            $className = $typeName;
        }


        $findPage = null;
        foreach ($pluDirs as $pluDir) {
            $findPage = $this->getTypeInfo($typeName, $pluDir);
            if (!empty($findPage)) {
                break;
            }
        }

        if (empty($findPage) && $typeName != self::$typesDef) {
            $typeName = self::$typesDef;
            $className = $typeName;
            foreach ($pluDirs as $pluDir) {
                $findPage = $this->getTypeInfo($typeName, $pluDir);
                if (!empty($findPage)) {
                    break;
                }
            }
        }

        if (empty($findPage)) {
            return '';
        }

        list($viewPath, $pluPath) = $findPage;

        $tplPath = "{$viewPath}.tpl";
        if ($pluPath == '') {
            $pluObj = plu();
        } else {
            $pluObj = plu($pluPath);
        }
        $tplObj = $pluObj->tpl;
        if (empty($tplObj)) {
            $tplObj = \Tphp\Config::$tpl;
        }
        if ($onEachSide <= 0) { //默认为系统条数处理
            $html = $pages->links($tplPath, [
                'oneachside' => 3,
                'plu' => $pluObj,
                'tpl' => $tplObj
            ]);
        } else { //自定义分页
            $window = \Illuminate\Pagination\UrlWindow::make($pages, $onEachSide);
            $elements = array_filter([
                $window['first'],
                is_array($window['slider']) ? '...' : null,
                $window['slider'],
                is_array($window['last']) ? '...' : null,
                $window['last'],
            ]);

            $html = view($tplPath, [
                'paginator' => $pages,
                'elements' => $elements,
                'oneachside' => $onEachSide,
                'plu' => $pluObj,
                'tpl' => $tplObj
            ]);
        }
        unset($pages);
        $config = [
            'html' => $html
        ];

        if (!empty($className) && $typeName != $className) {
            $classPath = "plugins/{$pluPath}/page/{$className}";
            if (!view()->exists("{$classPath}.tpl")) {
                $classPath = "page/{$className}";
                if (!view()->exists("{$classPath}.tpl")) {
                    $classPath = null;
                }
            }

            if (!empty($classPath)) {
                $config['class'] = $classPath;
            }
        }
        $html = tpl("/" . $viewPath . ".html", $config);
        return $html;
    }

}
