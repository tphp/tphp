<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl\Init;
use Tphp\Basic\Tpl\Run;
use Tphp\Basic\Tpl\Method as TplMethod;

/**
 * 方法类
 * Trait Method
 * @package Tphp\Basic\Tpl\Init
 */
trait Method
{
    /**
     * 运行方法
     * @param $function
     * @param $args
     * @return mixed
     * @throws \ReflectionException
     */
    public static function runMethodFunction($function, &...$args)
    {
        if (!is_function($function)) {
            return $function;
        }

        $argsLen = count($args);
        $argsArr = [];
        for ($i = 0; $i < $argsLen; $i ++) {
            $argsArr[] = "\$args[{$i}]";
        }
        $argsStr = implode(', ', $argsArr);

        eval("\$retFun = \$function({$argsStr});");
        return $retFun;
    }

    /**
     * 获取类中的方法执行代码
     * @param $class
     * @param $method
     */
    private static function getClassMethodCode($class, $method, &$args)
    {
        $params = $method->getParameters();
        $paramArr = [];
        $paramNames = [];
        $paramSrcNames = [];
        foreach ($params as $param) {
            $arr = [
                'name' => $param->name
            ];
            $name = "\$" . $param->name;
            if ($param->isPassedByReference()) {
                $name = "&{$name}";
            }

            if ($param->hasType()) {
                $typeName = $param->getType()->getName();
                $name = "{$typeName} {$name}";
            }

            if ($param->isDefaultValueAvailable()) {
                $arr['value'] = $param->getDefaultValue();
                $name = "{$name} = null";
            }
            $paramArr[] = $arr;
            $paramNames[] = $name;
            $paramSrcNames[] = "$" . $param->name;
        }
        $paramStr = implode(", ", $paramNames);
        $paramSrcStr = implode(", ", $paramSrcNames);
        $isStatic = $method->isStatic();
        $methodName = $method->getName();

        $classStatic = '// Nothing';
        $classObject = null;
        if (is_string($class)) {
            if ($isStatic) {
                $classStatic = "return " . $class;
            } else {
                $classObject = new $class(...$args);
            }
        } elseif (is_object($class)) {
            if ($isStatic) {
                $classStatic = "return " . get_class($class);
            } else {
                $classObject = $class;
            }
        } else {
            return;
        }

        $codeStr = <<<EOF
\$retFun = function({$paramStr}) use(\$paramArr, \$classObject, \$methodName, \$isStatic)  {
    \$num = func_num_args();
    \$paLen = count(\$paramArr);
    if (\$paLen > \$num) {
        for (\$i = \$num; \$i < \$paLen; \$i ++) {
            \$value = \$paramArr[\$i]['value'];
            if (!isset(\$value)) {
                continue;
            }
            \$name = \$paramArr[\$i]['name'];
            \${\$name} = \$value;
        }
    }

    if (\$isStatic) {
        {$classStatic}::{$methodName}({$paramSrcStr});
    } else {
        return \$classObject->{$methodName}({$paramSrcStr});
    }
};
EOF;

        eval($codeStr);
        return $retFun;
    }

    /**
     * 获取类中的函数以供后期调用,返回方法
     * @param null $class
     * @param string $methodName
     * @param bool $isNew
     * @throws \ReflectionException
     */
    public static function getClassMethod($class = null, $methodName = '', bool $isNew = false)
    {
        $retMethodArray = self::getClassMethodArray($class, $methodName, $isNew);
        if (empty($retMethodArray) || !is_array($retMethodArray)) {
            return;
        }

        return $retMethodArray[0]();
    }

    /**
     * 获取类中的函数以供后期调用,返回方法数组
     * @param null $class
     * @param string $methodName
     * @param bool $isNew
     * @throws \ReflectionException
     */
    private static function getClassMethodArray($class = null, $methodName = '', bool $isNew = false)
    {
        if (!is_string($methodName)) {
            return;
        }

        $methodName = trim($methodName);
        if(empty($methodName)) {
            return;
        }

        $args = [];
        if (is_array($class)) {
            $args = array_values($class);
            $class = $args[0];
            unset($args[0]);
            $args = array_values($args);
        }

        if (is_object($class)) {
            $classRef = new \ReflectionClass($class);
        } elseif (is_string($class)) {
            $class = trim($class);
            if (!class_exists($class)) {
                return;
            }
            $classRef = new \ReflectionClass($class);
        } else {
            return;
        }

        if (!$classRef->hasMethod($methodName)) {
            return;
        }

        $method = $classRef->getMethod($methodName);
        if (!$method->isPublic()) {
            return;
        }

        return [function ($isOnlyStatic = false) use ($class, $method, $isNew, $args) {
            if (is_string($class) && !$method->isStatic()) {
                $classKeyName = $class;
                if (isset(self::$newClassInc[$class])) {
                    $classKeyName .= "#" . self::$newClassInc[$class];
                } elseif ($isNew) {
                    self::$newClassInc[$class] = 0;
                }

                if ($isNew) {
                    self::$newClassInc[$class] ++;
                }

                $newClass = self::$newClass[$classKeyName];
                if (empty($newClass)) {
                    // 如果仅调用静态变量时，不会创建新的类
                    if ($isOnlyStatic) {
                        return;
                    }
                    $newClass = new $class(...$args);
                    self::$newClass[$classKeyName] = $newClass;
                }

                $class = $newClass;
            }

            return self::getClassMethodCode($class, $method, $args);
        }];
    }

    /**
     * 初始化方法
     * @param null $methodKeyName
     * @return Method
     */
    private static function getMethodInstance($methodKeyName = null)
    {
        if (empty($methodKeyName)) {
            if (empty(self::$tplMethodEmpty)) {
                self::$tplMethodEmpty = new TplMethod();
            }
            return self::$tplMethodEmpty;
        }

        $methodInit = new TplMethod();
        $methodInit->keyName = $methodKeyName;
        return $methodInit;
    }

    /**
     * 获取空方法
     * @return \Closure
     */
    private static function getMethodEmpty()
    {
        if (!empty(self::$methodEmpty)) {
            return self::$methodEmpty;
        }
        self::$methodEmpty = function () {

        };
        return self::$methodEmpty;
    }

    /**
     * 设置系统类中方法
     * @param null $methodKeyName 唯一键值
     * @param null $class 类或类名
     * @param null $method 方法或函数
     * @param bool $isExit 是否退出
     * @param bool $isNew 是否新建类
     * @throws \ReflectionException
     */
    public static function methodForClass($methodKeyName = null, $class = null, $method = null, bool $isNew = false)
    {
        if (!is_string($methodKeyName) || empty($class) || !is_string($method)) {
            return self::getMethodInstance();
        }
        $methodKeyName = trim($methodKeyName);
        $method = trim($method);
        if (empty($method) || empty($methodKeyName) || isset(self::$methods[$methodKeyName])) {
            return self::getMethodInstance($methodKeyName);
        }

        $classMethod = self::getClassMethodArray($class, $method, $isNew);
        if (empty($classMethod)) {
            return self::getMethodInstance($methodKeyName);
        }

        self::$methods[$methodKeyName] = $classMethod;
        return self::getMethodInstance($methodKeyName);
    }

    /**
     * 设置自定义方法
     * @param null $methodKeyName 唯一键值
     * @param null $method 方法或函数
     * @param bool $isExit 是否退出
     */
    public static function method($methodKeyName = null, $method = null)
    {
        if (!is_string($methodKeyName) || empty($method)) {
            return self::getMethodInstance();
        }
        $methodKeyName = trim($methodKeyName);
        if (empty($methodKeyName) || isset(self::$methods[$methodKeyName])) {
            return self::getMethodInstance($methodKeyName);
        }
        self::$methods[$methodKeyName] = $method;
        return self::getMethodInstance($methodKeyName);
    }

    /**
     * 获取方法
     * @param null $methodKeyName
     */
    public static function getMethod($methodKeyName = null)
    {
        if (empty($methodKeyName) || !is_string($methodKeyName)) {
            return self::getMethodEmpty();
        }
        $methodKeyName = trim($methodKeyName);
        if (!self::hasMethod($methodKeyName)) {
            return self::getMethodEmpty();
        }
        return self::$methods[$methodKeyName];
    }

    /**
     * 运行方法
     * @param null $methodKeyName
     * @return array|null|string
     */
    public static function runMethod($methodKeyName = null, ...$args)
    {
        return self::runMethodPointer($methodKeyName, ...$args);
    }

    /**
     * 判断方法是否存在
     * @param null $methodKeyName
     * @return bool
     */
    public static function hasMethod($methodKeyName = null) : bool
    {
        if (!is_string($methodKeyName)) {
            return false;
        }

        $methodKeyName = trim($methodKeyName);
        if (empty($methodKeyName)) {
            return false;
        }

        if (empty($methodKeyName) || !isset(self::$methods[$methodKeyName])) {
            return false;
        }

        $method = self::$methods[$methodKeyName];
        if (is_function($method)) {
            return true;
        }

        if (is_array($method) && count($method) > 0) {
            $isOnlyStatic = self::$methodOnlyStatics[$methodKeyName];
            if (!is_bool($isOnlyStatic)) {
                $isOnlyStatic = false;
            }
            $method = $method[0]($isOnlyStatic);
            if (is_function($method)) {
                self::$methods[$methodKeyName] = $method;
                return true;
            } else {
                unset(self::$methods[$methodKeyName]);
            }
        }
        return false;
    }

    /**
     * 运行方法
     * 指针方式运行
     * @param null $methodKeyName
     * @return array|null|string
     */
    public static function runMethodPointer($methodKeyName = null, &...$args)
    {
        if (!is_string($methodKeyName)) {
            return null;
        }
        $methodKeyName = trim($methodKeyName);

        if (!self::hasMethod($methodKeyName)) {
            return null;
        }

        $method = self::$methods[$methodKeyName];
        if (is_function($method)) {
            $method = self::runMethodFunction($method, ...$args);
        }

        // 如果运行有退出
        $obj = $args[0];
        if (is_object($obj) && !is_null($obj->exitJson) && $obj->exitJson !== '') {
            Run::exitJson([$obj->exitJson]);
            return null;
        }

        $args = [];
        if (!empty($method)) {
            $args[] = $method;
        }

        if (self::$methodExits[$methodKeyName] ?? false) {
            Run::exitJson($args);
            return null;
        }
        return Run::getExitJson($args, true);
    }

    /**
     * 自动运行设定方法
     * @param string $methodKeyName
     * @param string $flagStr
     * @return array|null|string
     */
    public static function runMethodAuto($methodKeyName = '', String $flagStr = '', $obj = null)
    {
        if (!is_string($methodKeyName)) {
            return null;
        }

        $methodKeyName = trim($methodKeyName);
        $flagStr = trim($flagStr);
        if (!empty($flagStr)) {
            $methodKeyName = "{$flagStr}{$methodKeyName}";
        }

        if (!self::hasMethod($methodKeyName)) {
            return null;
        }

        if (isset(self::$methodAutos[$methodKeyName]) && self::$methodAutos[$methodKeyName] === false) {
            return null;
        }

        list($isPointer, $args) = self::$methodArgs[$methodKeyName];
        if (empty($args)) {
            $args = [$obj];
        } else {
            array_unshift($args, $obj);
        }
        if ($isPointer) {
            return self::runMethodPointer($methodKeyName, ...$args);
        } else {
            return self::runMethod($methodKeyName, ...$args);
        }
    }

    /**
     * 获取类中的参数
     * @param Object|null $class
     * @param string $property
     * @return null
     * @throws \ReflectionException
     */
    public static function getProperty(Object $class = null, $property = '')
    {
        if (!is_object($class) || !is_string($property)) {
            return null;
        }

        $property = trim($property);
        if (empty($property)) {
            return null;
        }

        $retProperty = null;
        $classRef = new \ReflectionClass($class);
        if ($classRef->hasProperty($property)) {
            $methodObj = $classRef->getProperty($property);
            if ($methodObj->isPublic()) {
                if ($methodObj->isStatic()) {
                    $retProperty = $class::${$property};
                } else {
                    $retProperty = $class->{$property};
                }
            }
        }
        return $retProperty;
    }
    
    /**
     * 设置类中的参数
     * @param Object|null $class
     * @param string $property
     * @return null
     * @throws \ReflectionException
     */
    public static function setProperty(Object $class = null, $property = '', $value = null)
    {
        if (!is_object($class) || !is_string($property)) {
            return;
        }

        $property = trim($property);
        if (empty($property)) {
            return;
        }

        $classRef = new \ReflectionClass($class);
        if ($classRef->hasProperty($property)) {
            $methodObj = $classRef->getProperty($property);
            if ($methodObj->isPublic()) {
                if ($methodObj->isStatic()) {
                    $class::${$property} = $value;
                } else {
                    $class->{$property} = $value;
                }
            }
        }
    }
}
