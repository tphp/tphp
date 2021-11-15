<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl;

use Tphp\Basic\Plugin\Init as PluginInit;
use Tphp\Register;
use Tphp\Config as TphpConfig;

class Handle
{
    use Handle\Commands;
    use Handle\LoadStatic;
    use Handle\Plugin;

    private static $browser = null;
    private static $browserVersion = null;

    public function __construct()
    {
        $tplBase = Register::getHtmlPath();
        $this->tplBase = base_path($tplBase); //TPL根目录
        $this->tplCache = env("TPL_CACHE");
        if (!is_bool($this->tplCache)) $this->tplCache = false;

        list($browser, $browserVersion) = Run::getBrowser();
        self::$browser = strtolower(trim($browser));
        self::$browserVersion = strtolower(trim($browserVersion));
    }

    /**
     * 显示Tpl代码
     * @param string $tpl Tpl模板路径
     * @param array $config 数据配置
     * @param string $runName 运行名称，run为模板，runJson为接口
     * @return mixed
     */
    public static function start($tpl = '', $config = [], $runName = "run", $isArray = false)
    {
        if ($tpl === false) {
            // 如果模板路径为false则返回TPL对象
            if (!empty(TphpConfig::$tpl)) {
                return TphpConfig::$tpl;
            }
            TphpConfig::$tpl = new Init();
            TphpConfig::$tpl->isDomainStart = true;
            return TphpConfig::$tpl;
        }
        if (is_null(Register::$tplPath)) {
            return (new \Tphp\Domains\DomainsController($tpl, $config))->tpl();
        }
        $tpl = str_replace("\\", "/", $tpl);
        $tmpRootTpl = Init::$rootTpl; //嵌套调用循环
        $obj = (new Init())->__init($tpl, $tmpRootTpl);
        $gpp = PluginInit::getPluginPaths();
        if (empty($gpp['tpl'])) {
            $viewData = $gpp['viewData'];
            if (!empty($viewData)) {
                $obj->setView($viewData);
                unset(TphpConfig::$plugins['viewData']);
            }
        }
        TphpConfig::$plugins['tpl'] = $obj;

        (empty($config) || !is_array($config)) && $config = [];

        if (!empty($config)) {
            $tmpGet = [];
            $tmpPost = [];
            if (!empty($config['get'])) { //GET参数模拟处理
                $tmpGet = $_GET;
                foreach ($config['get'] as $key => $val) {
                    $_GET[$key] = $val;
                }
            }

            if (!empty($config['post'])) { //POST参数模拟处理
                $tmpPost = $_POST;
                foreach ($config['post'] as $key => $val) {
                    $_POST[$key] = $val;
                }
            }

            $obj->addConfig($config);
            $ret = $obj->$runName($isArray);
            !empty($config['get']) && $_GET = $tmpGet;
            !empty($config['post']) && $_POST = $tmpPost;
        } else {
            $ret = $obj->$runName($isArray);
            if (is_array($ret)) {
                foreach ($ret as $key => $val) {
                    if ($key === '_' && empty($val)) {
                        return "";
                    }
                }
            }
        }

        if (!empty($tmpRootTpl)) {
            Init::$rootTpl = $tmpRootTpl;
        }
        
        if (is_array($ret)) {
            if ($obj->isDomainStart) {
                if ($ret[0] !== true && count($ret) !== 2) {
                    $ret[] = $obj;
                }
            } else {
                $ret = $ret[1];
            }
        }

        return $ret;
    }
}
