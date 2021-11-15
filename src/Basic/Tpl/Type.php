<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl;

/**
 * 方法设置
 * Class Method
 * @package Tphp\Basic\Tpl
 */
class Type
{

    private static $types = [];

        /**
     * 类型名称
     * @var string
     */
    private $typeName = '';

    /**
     * TPL 类型名称
     * @var string
     */
    private $tplTypeName = '';

    /**
     * 初始化
     * Type constructor.
     * @param string $typeName
     * @param string $tplTypeName
     */
    function __construct($typeName = '', $tplTypeName = '')
    {
        $this->typeName = $typeName;
        $this->srcTypeName = $tplTypeName;
    }

    /**
     * 初始化类型
     * @param string $typeName
     * @param string $tplTypeName
     * @return mixed
     */
    public static function __init($typeName = '', $tplTypeName = '')
    {
        if (!is_string($typeName)) {
            $typeName = "";
        }
        $typeName = trim($typeName);

        if (isset(self::$types[$typeName])) {
            return self::$types[$typeName];
        }

        if (!is_string($tplTypeName)) {
            $tplTypeName = "";
        }
        $tplTypeName = trim($tplTypeName);

        self::$types[$typeName] = new static($typeName, $tplTypeName);
        return self::$types[$typeName];
    }

    /**
     * 获取执行后的方法
     * @param string $method
     * @return string|void
     */
    private function __get($method = '')
    {
        if (empty($this->typeName) || $this->typeName !== $this->srcTypeName) {
            return;
        }

        if (is_function($method)) {
            $method = $method();
        }

        return $method;
    }

    /**
     * 仅运行方法
     * @param string $method
     * @return string|void
     */
    public function run($method = '')
    {
        return $this->__get($method);
    }

    /**
     * 执行方法后退出并返回JSON格式数据
     * @param string $method
     */
    public function exit($method = '')
    {
        $method = $this->__get($method);
        while (is_function($method)) {
            $method = $method();
        }
        EXITJSON($method);
    }
    
}
