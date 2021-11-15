<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Plugin;

/**
 * Plugin RunJs
 */
class PluginRunJs
{
    function __construct($id = '', $cacheId = '', $path = '', $args = [], PluginClass $plu)
    {
        $this->id = $id;
        $this->cacheId = $cacheId;
        $this->path = $path;
        $this->viewPath = $path;
        $this->args = $args;
        $this->plu = $plu;
    }

    /**
     * 获取 id 属性值
     * @return string
     */
    public function getId()
    {
        if (empty($this->id)) {
            return '';
        }

        return 'id="' . $this->id . '"';
    }

    /**
     * 设置视图路径
     * @param string $path
     */
    public function setViewPath($path = '')
    {
        if (is_string($path)) {
            $path = trim($path);
            if (!empty($path)) {
                $this->viewPath = $path;
                return $this;
            }
        }
        
        $this->viewPath = $this->path;
        return $this;
    }

    /**
     * 设置参数
     * @param mixed ...$args
     */
    public function setArgs(...$args)
    {
        $runJsIds = \Tphp\Config::$obStart['imports']['runJsIds'];
        if (empty($runJsIds)) {
            return;
        }

        $cacheId = $this->cacheId;
        $runJsId = $runJsIds[$cacheId][1];
        if (empty($runJsId)) {
            $runJsId = [];
        }
        foreach ($args as $index => $arg) {
            $rArg = $runJsId[$index];
            if (is_array($rArg) && is_array($arg)) {
                foreach ($rArg as $k => $v) {
                    if (!isset($arg[$k])) {
                        $arg[$k] = $v;
                    }
                }
            }
            $runJsId[$index] = $arg;
        }

        \Tphp\Config::$obStart['imports']['runJsIds'][$cacheId][1] = $runJsId;
    }

    /**
     * 设置页面内css代码，用作于样式微调
     * @param string $scssCode
     * @param string $prevMessage
     * @return $this
     */
    public function style($scssCode = '', $prevMessage = '')
    {
        $this->plu->style($scssCode, $prevMessage);
        return $this;
    }

    /**
     * 设置页面内js代码，用作于脚本微调
     * @param string $jsCode
     * @param string $prevMessage
     * @return $this
     */
    public function script($jsCode = '', $prevMessage = '')
    {
        $this->plu->script($jsCode, $prevMessage);
        return $this;
    }
    
    /**
     * 打印视图
     * @param array $data
     * @param bool $errPrint 默认返回信息， 如果是字符串则直接返回， 如果为true则打印路径和数据
     * @param bool $returnPath 是否返回路径
     * @return string
     */
    public function view($data = [], $errPrint = true, $returnPath = false)
    {
        if (empty($data) || !is_array($data)) {
            $data = [];
        }

        $data['id'] = $this->getId();
        $data['__id__'] = $this->id;
        $data['runJs'] = $this;
        $args0 = $this->args[0];
        if (is_array($args0)) {
            unset($args0['id']);
            unset($args0['__id__']);
            foreach ($args0 as $key => $val) {
                if (is_int($key)) {
                    continue;
                }
                $key = trim($key);
                if (!preg_match('/^[^0-9]\w+$/', $key)) {
                    continue;
                }
                $data[$key] = $val;
            }
        }
        $path = $this->viewPath;
        if (empty($path)) {
            $path = $this->path;
        }
        return $this->plu->view($path, $data, $errPrint, $returnPath);
    }
}
