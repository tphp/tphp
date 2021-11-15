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
 * 工具类
 * Trait Method
 * @package Tphp\Basic\Tpl\Init
 */
trait Tools
{

    /**
     * 获取页面字段传递
     */
    public function getView($keyName = '')
    {
        if (empty($keyName)) {
            if (!is_string($keyName)) {
                return null;
            }

            $vDataStatic = self::$viewDataStatic;
            $vData = $this->viewData;
            foreach ($vData as $key => $val) {
                $vDataStatic[$key] = $val;
            }

            return $vDataStatic;
        }

        if (!is_string($keyName)) {
            return null;
        }

        return $this->viewData[$keyName] ?? self::$viewDataStatic[$keyName];
    }

    /**
     * 设置页面字段传递
     */
    public function setView()
    {
        $argsNum = func_num_args();
        if ($argsNum <= 0) return;
        $args = func_get_args();
        $args0 = $args[0];
        $vData = $this->viewData;
        if ($argsNum == 1) {
            if (is_array($args0)) {
                foreach ($args0 as $key => $val) {
                    if (is_string($key) && !empty($val)) {
                        if ($key[0] == ':') {
                            $key = substr($key, 1);
                            self::$viewDataStatic[$key] = $val;
                        } else {
                            $vData[$key] = $val;
                        }
                    }
                }
            } else {
                return;
            }
        } else {
            if ($args0[0] == ':') {
                $args0 = substr($args0, 1);
                self::$viewDataStatic[$args0] = $args[1];
            } else {
                $vData[$args0] = $args[1];
            }
        }
        $this->viewData = $vData;
    }

    /**
     * 获取Cookie值实时的值传递
     * @param $key
     * @param string $default
     * @return array|mixed|string
     */
    public function getCookie($keyName = "", $default = "")
    {
        $cookiesNow = $this->cookiesNow;
        if (empty($keyName)) return $cookiesNow;
        if (isset($cookiesNow[$keyName])) return $cookiesNow[$keyName];
        return $default;
    }

    /**
     * 设置Cookies
     */
    public function setCookie()
    {
        $argsNum = func_num_args();
        if ($argsNum <= 0) return;
        $args = func_get_args();
        $args0 = $args[0];
        if ($argsNum == 1) {
            if (is_array($args0)) {
                if (is_string($args0[0]) || is_numeric($args0[0])) {
                    $cList = [$args0];
                } else {
                    $cList = $args0;
                }
            } else {
                return;
            }
        } else {
            $cList = [$args];
        }

        $cookies = $this->cookies;
        $cookiesNow = $this->cookiesNow;
        $cookiesForget = $this->cookiesForget;
        foreach ($cList as $val) {
            if (count($val) < 2) continue;
            $val0 = $val[0];
            if (is_numeric($val0)) $val0 = $val0 . "";
            if (!is_string($val0)) continue;

            $val1 = $val[1];
            if (is_array($val1) || is_object($val1)) { //如果是数组或对象则转化为json字符串数据
                $val1 = json_encode($val1, JSON_UNESCAPED_UNICODE);
            } elseif (is_string($val1) || is_numeric($val1)) { //如果是字符串或数字直接转换为字符串
                $val1 = $val1 . "";
            } elseif (!is_bool($val1)) { //如果不为bool值则直接返回
                continue;
            }

            $expire = 0; //过期时间，0为永不过期
            if (isset($val[2])) {
                $val2 = $val[2];
                if (is_numeric($val2) && $val2 > 0) {
                    $expire = $val2;
                }
            }
            $cookies[] = [$val0, $val1, $expire];
            $cookiesNow[$val0] = $val1;
            $key = array_search($val0, $cookiesForget);
            if ($key !== false) {
                unset($cookiesForget[$key]);
            }
        }
        $this->cookies = $cookies;
        $this->cookiesNow = $cookiesNow;
        $this->cookiesForget = $cookiesForget;
    }

    /**
     * 删除cookies
     */
    public function forgetCookie()
    {
        $cookiesNow = $this->cookiesNow;
        $cookiesForget = $this->cookiesForget;
        $cookies = $this->cookies;
        $args = func_get_args();
        foreach ($args as $val) {
            is_numeric($val) && $val .= "";
            if (is_string($val)) {
                $cookiesForget[] = $val;
                unset($cookies[$val]);
                unset($cookiesNow[$val]);
            }
        }
        $this->cookiesForget = array_unique($cookiesForget);
        $this->cookiesNow = $cookiesNow;
        $this->cookies = $cookies;
    }

    /**
     * 删除所有cookies
     */
    public function forgetAllCookie()
    {
        $this->cookies = [];
        $cookiesForget = [];
        $cookiesNow = $this->cookiesNow;
        $this->cookiesNow = [];
        foreach ($cookiesNow as $key => $val) {
            $cookiesForget[] = $key;
        }
        $this->cookiesForget = $cookiesForget;
    }

    /**
     * 获取数据保存信息
     * @param null $keyName
     * @return array|mixed|null
     */
    public function getHandle($keyName = null)
    {
        if (!$this->isPost()) {
            return null;
        }
        if (!empty($keyName) && !is_string($keyName)) {
            return null;
        }
        if (!in_array($this->tplType, ['add', 'edit', 'handle'])) {
            return null;
        }

        if ($this->tplType == 'add') {
            $ttype = 'add';
        } else {
            $ttype = 'edit';
        }
        $ret = [];
        $tConf = $this->config;
        if (is_array($tConf['config'])) {
            if (!empty($tConf['config'][$ttype])) {
                $ret = $tConf['config'][$ttype];
            }
        }
        if (empty($keyName)) {
            return $ret;
        } else {
            return $ret[$keyName];
        }
    }

    /**
     * 设置数据保存操作
     * @param null $obj
     * @param null $value
     */
    public function setHandle($obj = null, $value = null)
    {
        $post = [];
        if (is_string($obj)) {
            $post[$obj] = $value;
        } elseif (is_array($obj)) {
            foreach ($obj as $key => $val) {
                if (is_string($key)) {
                    $post[$key] = $val;
                }
            }
        }
        if (empty($post)) {
            return;
        }
        if (in_array($this->tplType, ['add', 'edit', 'handle'])) {
            if ($this->tplType == 'add') {
                $ttype = 'add';
            } else {
                $ttype = 'edit';
            }
            $tConf = &$this->config;
            if (is_array($tConf['config'])) {
                if (empty($tConf['config'][$ttype])) {
                    $tConf['config'][$ttype] = [];
                }
                foreach ($post as $key => $val) {
                    if (!isset($val)) {
                        unset($tConf['config'][$ttype][$key]);
                    } else {
                        $tConf['config'][$ttype][$key] = $val;
                    }
                }
            }
        }
    }

    /**
     * 获取字段数据
     * @param null $keyName
     * @return array|mixed|null
     */
    public function getField($keyName = null)
    {
        $field = $this->getView('field');
        if (empty($keyName) || !is_string($keyName)) {
            return $field;
        }
        return $field[$keyName];
    }

    /**
     * 获取字段数据
     * @param null $obj
     * @param null $value
     */
    public function setField($obj = null, $value = null)
    {
        $newField = [];
        if (is_string($obj)) {
            $newField[$obj] = $value;
        } elseif (is_array($obj)) {
            foreach ($obj as $key => $val) {
                if (is_string($key)) {
                    $newField[$key] = $val;
                }
            }
        }
        if (empty($newField)) {
            return;
        }
        if (empty($this->handleField)) {
            $this->handleField = $newField;
        } else {
            foreach ($newField as $key => $val) {
                $this->handleField[$key] = $val;
            }
        }
        $field = $this->getField();
        if (empty($field) || !is_array($field)) {
            $field = $newField;
        } else {
            foreach ($newField as $key => $val) {
                $field[$key] = $val;
            }
        }
        $this->setView('field', $field);
    }
}
