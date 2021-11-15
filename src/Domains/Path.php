<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Domains;

use Tphp\Register;

/**
 * 初始化路径设置
 * Class Path
 */
class Path
{
    public $tplPath;

    function __construct($tpl = '')
    {
        list($this->tplPath, $this->tplType, $this->args) = $this->getTplPath($tpl);
        $tplBase = Register::getHtmlPath(true) . "/";
        $topPath = Register::getTopPath("/");
        $realPath = $tplBase . $topPath . "_init.php";
        if (file_exists($realPath)) {
            include_once $realPath;
        }

        $dc = \Tphp\Config::$domain;
        $isInit = false;
        $dcInit = '';
        if (is_array($dc) && is_string($dc['init'])) {
            $dcInit = trim($dc['init']);
            if (class_exists($dcInit)) {
                $isInit = true;
            }
        }

        if (!$isInit) {
            $dcInit = \Tphp\Controller::class;
        }

        // 动态加载 InitController， 确保控制器存在
        $extends = <<<EOF
namespace {
    if (!class_exists("InitController")) {
        class InitController extends \\{$dcInit} {}
    }
}
EOF;
        eval($extends);

        $mainPath = $this->getMainPath();
        if (!empty($mainPath)) {
            $this->plu = plu($mainPath);
            $this->plu->runMain();
        }
    }

    /**
     * 获取主路径入口
     * @return string
     */
    private function getMainPath()
    {
        $envMain = env('MAIN', Register::$mainPath);
        $envMain = strtolower($envMain);
        $envMain = str_replace("\\", ".", $envMain);
        $envMain = str_replace("/", ".", $envMain);
        $envMainArr = explode(".", $envMain);
        $envMainNew = [];
        foreach ($envMainArr as $em) {
            $em = trim($em);
            if (empty($em)) {
                continue;
            }
            $envMainNew[] = $em;
        }
        $envMainCot = count($envMainNew);
        if ($envMainCot >= 2) {
            // 加载插件配置
            $mainPath = $envMainNew[0] . "." . $envMainNew[1];
            unset($envMainNew[0]);
            unset($envMainNew[1]);
            if (!empty($envMainNew)) {
                $tphpMainWebPath = implode("/", $envMainNew);
                if (is_null(Register::$mainWebPath)) {
                    Register::$mainWebPath = $tphpMainWebPath;
                }
            }
        } else {
            $mainPath = '';
        }
        return $mainPath;
    }

    /**
     * 获取TPL路径
     * @param string $tpl
     * @return array
     */
    private function getTplPath($tpl = '')
    {
        if (empty($tpl)) {
            $request = Request();
            $a = [];
            for ($i = 1; $i < 10; $i++) {
                $apiName = "api_name_{$i}";
                $arg = $request->$apiName;
                if (empty($arg) || trim($arg) == "") {
                    break;
                }
                $a[] = $arg;
            }
            $tpl = implode("/", $a);
        }

        $pos = strrpos($tpl, ".");
        if ($pos <= 0) {
            $type = "#";
        } else {
            $type = substr($tpl, $pos + 1);
            $tpl = substr($tpl, 0, $pos);
            if (empty($type) && $type != '0') {
                $type = '#';
            }
        }

        $args = \Tphp\Config::$domain['args'];
        $args = str_replace("\\", "/", $args);
        $args = trim(trim($args, '/'));
        $argsInfo = [];
        if (!empty($args)) { //URL路径args传递
            $argsArr = explode("/", $args);
            $argsNew = [];
            foreach ($argsArr as $val) {
                $val = trim($val);
                !empty($val) && $argsNew[] = $val;
            }
            if (!empty($argsNew)) {
                $tplArr = explode("/", $tpl);
                $tplNew = [];
                foreach ($tplArr as $val) {
                    $val = trim($val);
                    !empty($val) && $tplNew[] = $val;
                }
                if (count($tplNew) < count($argsNew)) {
                    __exit("参数传递错误，URL参数代码：/" . implode("/", $argsNew) . " 当前TPL： {$tpl}");
                }
                $argsV = [];
                $delKey = [];
                $tplVal = [];
                foreach ($argsNew as $key => $val) {
                    $argsV[$val] = $tplNew[$key];
                    $delKey[] = $key;
                    $tplVal[] = $tplNew[$key];
                }
                foreach ($delKey as $val) {
                    unset($tplNew[$val]);
                }
                $tpl = implode("/", $tplNew);
                empty($tpl) && $tpl = 'index';
                $argsInfo['info'] = $argsV;
                $argsInfo['url'] = "/" . implode("/", $tplVal);;
            }
        }

        return [$tpl, $type, $argsInfo];
    }
}
