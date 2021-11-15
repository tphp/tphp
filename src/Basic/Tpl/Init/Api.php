<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl\Init;

/**
 * 接口类
 * Trait Method
 * @package Tphp\Basic\Tpl\Init
 */
trait Api
{
    use Method;

    public static $methods = [];   // 调用方法，以 tplType 为键值，不可叠加
    public static $methodExits = [];   // 调用方法退出页面
    public static $methodAutos = [];   // 自动运行设置，默认自动执行
    public static $methodOnlyStatics = [];   // 是否仅使用静态方法
    public static $methodArgs = [];   // 调用方法默认参数传递

    protected static $methodEmpty;   // 空调用方法，避免出错
    protected static $tplMethodEmpty;   // 空调用方法，避免出错
    protected static $newClass = [];   // 自动生成类存储空间
    protected static $newClassInc = [];   // 重新自动生成类存储空间

    private static $isUseApiInit = false; // 保证初始化仅加载一次

    public function __construct()
    {
        if (get_parent_class()) {
            $domainPath = \Tphp\Config::$domainPath;
            $this->__apiInit($domainPath->tplType);
            if (method_exists(parent::class, '__construct')) {
                parent::__construct($domainPath->tplPath, $domainPath->tplType, $domainPath->args);
            }
        }
    }

    /**
     * 获取模板后缀
     * @param $tplType
     * @return mixed
     */
    private function __apiCheckTplType(String $tplType)
    {
        $tplType = trim($tplType);
        if (empty($tplType) && $tplType != '0') {
            return;
        }

        // 数字类型
        if (is_numeric($tplType)) {
            __abort();
        }

        if (strlen($tplType) == 1) {
            $tplType .= '0';
        }

        // 方法验证，必须由字母数字或下划线组成，并且首字母不能为数字 ":" 和 下划线
        if (preg_match('/^[^:_0-9]\w+$/', $tplType)) {
            return;
        }

        __abort();
    }

    /**
     * 自动运行方法
     * @param $tplType
     * @param $flag
     */
    private function __apiRunMethodAuto($tplType, $flag)
    {
        $tplObj = \Tphp\Config::$tpl;
        if (empty($tplObj)) {
            $tplObj = $this;
        }
        // 运行全局方法
        self::runMethodAuto($flag, '', $tplObj);

        $tplType = trim($tplType);
        if (empty($tplType) && $tplType != '0') {
            $tplType = 'html';
        }

        // 运行页面方法
        self::runMethodAuto($tplType, $flag, $tplObj);
    }

    /**
     * 初始化模板接口
     */
    private function __apiInit($tplType = '')
    {
        $isInit = $tplType === '#';

        if (self::$isUseApiInit) {
            !$isInit && $this->__apiRunMethodAuto($tplType, '_');
            return;
        }

        self::$isUseApiInit = true;

        !$isInit && $this->__apiCheckTplType($tplType);

        if (method_exists(parent::class, '__method')) {
            parent::__method();
        }

        !$isInit && $this->__apiRunMethodAuto($tplType, '_');
    }

    /**
     * 开始时执行数据
     */
    protected function __apiRunStart($tplType = '')
    {
        $this->__apiRunMethodAuto($tplType, ':');
    }

    /**
     * 结束时执行数据
     */
    protected function __apiRunEnd($tplType = '')
    {
        $this->__apiRunMethodAuto($tplType, '::');
    }
}
