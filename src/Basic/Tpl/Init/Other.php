<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl\Init;
use Tphp\Basic\Sql\SqlCache;

/**
 * 其他
 * Trait Method
 * @package Tphp\Basic\Tpl\Init
 */
trait Other
{

    private static $pluMain;
    private static $pluThis;

    /*
     * 设置初始化配置
     */
    public function setDataFlag($bool=true){
        $this->__data_flag = $bool;
    }

    // 处理文件类型的字段值
    public function getDataToDir($data = [], $dataType = "sql", $iniFile = "")
    {
        $retData = $this->getDataToIni($data, $dataType, $this->getIniInfo($dataType, $iniFile));
        if (is_array($retData) && is_array($retData[1])) {
            $this->getSetData($retData[1]);
        }
        return $retData;
    }

    /**
     * 获取主系统插件
     * @return mixed
     */
    public static function getPluMain()
    {
        if (!empty(self::$pluMain)) {
            return self::$pluMain;
        }

        self::$pluMain = \Tphp\Config::$domainPath->plu;
        return self::$pluMain;
    }

    /**
     * 获取主系统插件
     * @return mixed
     */
    public function pluMain()
    {
        return self::getPluMain();
    }

    /**
     * 获取当前系统插件
     * @return mixed
     */
    public static function getPluBasic()
    {
        if (!empty(self::$pluThis)) {
            return self::$pluThis;
        }

        $basePluPath = \Tphp\Config::$domainPath->basePluPath;
        if (empty($basePluPath)) {
            self::$pluThis = plu();
        } else {
            self::$pluThis = plu($basePluPath);
        }

        return self::$pluThis;
    }

    /**
     * 获取包含文件
     * @param string $file
     * @return bool|mixed
     */
    public static function includeFile($file = '')
    {
        if (file_exists($file)) {
            return include $file;
        }

        return null;
    }
    
    /**
     * 获取包含文件
     * @param string $file
     * @return bool|mixed
     */
    public function includeThisFile($file = '')
    {
        if (file_exists($file)) {
            return include $file;
        }

        return null;
    }

    /**
     * 获取当前系统插件
     * @return mixed
     */
    public function plu($pluDir = '')
    {
        if (is_string($pluDir)) {
            $pluDir = trim($pluDir);
        } else {
            $pluDir = '';
        }

        // 如果为空则使用系统默认插件
        if (empty($pluDir)) {
            if (empty($this->plu)) {
                $pluObj = self::getPluBasic();
            } else {
                $pluObj = $this->plu;
            }
        } else {
            $pluObj = plu($pluDir);
        }

        if (empty($pluObj->tpl)) {
            $pluObj->tpl = $this;
        }

        return $pluObj;
    }

    /**
     * 获取浏览器版本
     * @return array
     */
    public function getBrowser()
    {
        return [self::$browser, self::$browserVersion];
    }
}