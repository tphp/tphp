<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp;

/**
 * 全局配置信息
 * Class Config
 * @package Tphp
 */
class Config
{
    // 域名解析配置
    public static $domain = [];

    // 域名解析配置原始数据组
    public static $domains = [];

    // 域名路径配置
    public static $domainPath = [];

    // 页面重构
    public static $obStart = [];

    // 模板缓存
    public static $tpl = null;

    // 数据缓存
    public static $dataFileInfo = null;

    // 数据缓存叠加
    public static $dataFileInfoInc = [];

    // 插件缓存
    public static $plugins = null;
}
