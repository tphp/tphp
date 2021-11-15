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
class Method
{
    public $keyName;

    /**
     * 是否现在退出并返回值
     * @param bool $isAuto
     */
    public function auto(bool $isAuto = true)
    {
        if (empty($this->keyName)) {
            return $this;
        }
        Init::$methodAutos[$this->keyName] = $isAuto;
        return $this;
    }

    /**
     * 是否仅使用静态方法
     * @param bool $isOnlyStatic
     */
    public function onlyStatic(bool $isOnlyStatic = true)
    {
        if (empty($this->keyName)) {
            return $this;
        }
        Init::$methodOnlyStatics[$this->keyName] = $isOnlyStatic;
        return $this;
    }

    /**
     * 是否现在退出并返回值
     * @param bool $isExit
     */
    public function exit(bool $isExit = true)
    {
        if (empty($this->keyName)) {
            return $this;
        }
        Init::$methodExits[$this->keyName] = $isExit;
        return $this;
    }

    /**
     * 设置参数传递
     */
    public function args(...$args)
    {
        if (empty($this->keyName)) {
            return $this;
        }
        Init::$methodArgs[$this->keyName] = [false, &$args];
        return $this;
    }

    /**
     * 设置参数传递
     * 指针传递
     */
    public function argsPointer(&...$args)
    {
        if (empty($this->keyName)) {
            return $this;
        }
        Init::$methodArgs[$this->keyName] = [true, &$args];
        return $this;
    }

    /**
     * 获取方法
     * @return \Closure
     */
    public function get()
    {
        return Init::getMethod($this->keyName);
    }

    /**
     * 获取Args
     * @return array|mixed
     */
    public function getArgs()
    {
        $argsInfo = Init::$methodArgs[$this->keyName];
        if (empty($argsInfo)) {
            return [];
        }

        return $argsInfo[1];
    }

    /**
     * 判断是否存在方法
     * @return bool
     */
    public function exists(): bool
    {
        return Init::hasMethod($this->keyName);
    }

    /**
     * 实例实时调用
     * @param mixed ...$args
     * @return mixed
     */
    public function invoke(...$args)
    {
        return $this->get()(...$args);
    }

    /**
     * 实例实时调用，指针模式
     * @param mixed ...$args
     * @return mixed
     */
    public function invokePointer(&...$args)
    {
        return $this->get()(...$args);
    }

    /**
     * 运行预设实例
     * @return array|string|null
     */
    public function run()
    {
        if (!$this->exists()) {
            return null;
        }

        // 强制关闭自动运行
        $this->auto(false);
        
        if (empty(Init::$methodArgs[$this->keyName])) {
            return Init::runMethod($this->keyName);
        }

        list($isPointer, $args) = Init::$methodArgs[$this->keyName];
        if (empty($args)) {
            $args = [$obj];
        } else {
            array_unshift($args, $obj);
        }

        if ($isPointer) {
            return Init::runMethodPointer($this->keyName, ...$args);
        } else {
            return Init::runMethod($this->keyName, ...$args);
        }
    }
}
