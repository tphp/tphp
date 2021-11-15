<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl;

use Tphp\Register;
use Tphp\Config as TphpConfig;

if (class_exists("\MainController")) {
    class MainController extends \MainController
    {
        use Init\Api;
    }
} elseif (class_exists("\InitController")) {
    class MainController extends \InitController
    {
        use Init\Api;
    }
} else {
    class MainController
    {
        use Init\Api;
    }
}

class Init extends MainController
{
    use Init\Commands,
        Init\Compare,
        Init\Sql,
        Init\Tools,
        Init\Other;

    //配置文件重设， data.php 重新解析
    const API_CONFIG    = 'setApiConfig';

    //配置文件初始化系统 ini.php
    const API_INI       = 'setApiIni';

    //获取最终数据并处理
    const API_DATA      = 'getApiData';

    //外部接口运行解析
    const API_RUN       = 'setApiRun';

    //设置表字段信息
    const API_FIELD       = 'setApiField';

    public static $class = [];
    public static $js = [];
    public static $css = [];
    public static $thisTpl;
    public static $rootTpl = "";
    public static $isRoot = true;
    public static $topTpl = "";
    public static $cacheTime = 30 * 60; //过期时间30分钟
    public static $dataTypeList = ['sql', 'sqlfind', 'api', 'dir']; //查询格式
    public static $viewDataStatic = [];
    public static $browser = null;
    public static $browserVersion = null;

    private static $tmpField = []; //临时存储字段信息
    private static $tmpPages = []; //临时存储分页信息

    public $isDomainStart = false;
    public $dataIni = [
        'reset' => false,
        'data' => [],
        'ini' => []
    ];
    
    private $layout = null;
    public $viewData = [];
    public $config = null;
    public $setConfig = null;

    function __init($tpl = '', $rootTpl = '')
    {
        if (empty(TphpConfig::$tpl)) {
            list($browser, $browserVersion) = Run::getBrowser();
            self::$browser = strtolower(trim($browser));
            self::$browserVersion = strtolower(trim($browserVersion));
            TphpConfig::$tpl = $this;
            $this->isDomainStart = true;
        }
        if ($tpl === false) {
            $tplIsFalse = true;
        } else {
            $tplIsFalse = false;
        }
        $this->configTplBase = Register::getHtmlPath();
        $this->tplType = "";
        $tpl === false && $tpl = '';
        $tpl = $this->getTplPath($tpl);
        empty($this->tplType) && $this->tplType != '0' && $this->tplType = 'html';
        $this->tplInit = $tpl;
        empty(self::$topTpl) && !empty($tpl) && self::$topTpl = $tpl;
        $rtt = "";
        if (self::$isRoot) {
            $this->isRoot = true;
            self::$isRoot = false;
        } else {
            $this->isRoot = false;
            if (empty($rootTpl)) {
                $rtt = $this->getRealTplTop();
                !empty($rtt) && $rtt .= "/";
            }
        }
        $tpl = $this->getRealTpl($tpl, $rootTpl);
        $tpl = rtrim($tpl, "\\/ ");

        // 相对路由
        $dir = '';
        $dpBaseTplPath = TphpConfig::$domainPath->baseTplPath;
        if (!empty($dpBaseTplPath)) {
            $dpbLength = strlen($dpBaseTplPath);
            if (strlen($tpl) > $dpbLength && substr($tpl, 0, $dpbLength) == $dpBaseTplPath) {
                $dir = substr($tpl, $dpbLength);
            }
        }
        $this->dir = $dir;

        $this->isRoot = true;
        $this->tplName = $tpl;
        self::$thisTpl = $this->tplName; //当前模板路径
        $this->tpl = $tpl; //模板指向
        $this->tplBase = base_path($this->configTplBase); //TPL根目录

        $this->tplPath = Register::getViewPath($rtt . $tpl) . "/"; //TPL系统遍历路径
        if (!is_dir($this->tplPath)) {
            $tRtt = $this->getRealTplTop();
            !empty($tRtt) && $tRtt .= "/";
            $tPath = $this->tplBase . $tRtt . $tpl . "/";
            if (is_dir($tPath)) {
                $rtt = $tRtt;
                $this->tplPath = $tPath;
            }
        }

        $this->tplPath = str_replace('//', '/', $this->tplPath);
        $this->dataPath = $this->tplPath; //数据路径
        $this->class = $tpl; //样式路径
        $this->set = []; //设置TPL值，如果该值一旦设置则不进行数据库查询处理
        $this->html = '_#_#_'; //设置TPL的HTML代码，如果该值一旦设置则不进行任何处理
        $this->cacheIdMd5 = md5($_SERVER['HTTP_HOST'] . md5($this->getCacheIdStr($rtt . $tpl)));
        $this->cookies = []; //设置cookies
        $this->cookiesForget = []; //设置cookies
        $this->cookiesNow = \Cookie::get();
        $this->exitJson = "";
        $gdPath = $this->dataPath . "data.php";
        if (isset(TphpConfig::$dataFileInfoInc[$gdPath])) {
            unset(TphpConfig::$dataFileInfo[$gdPath]);
        } else {
            TphpConfig::$dataFileInfoInc[$gdPath] = true;
        }
        return $this;
    }

    /**
     * 开始加载模板
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|string
     */
    public function run($isArray = false)
    {
        $this->isArray = $isArray;
        $tpl = $this->tpl;
        if (!is_dir($this->tplPath)) {
            $this->plu = $this->getPluObject();
            self::$rootTpl = "";
            $isShowError = true;
            if (method_exists(parent::class, '__run')) {
                $ret = parent::__run();
                $ret && $isShowError = false;
            }
            if (method_exists(parent::class, '__last')) {
                $ret = parent::__last('');
                $ret && $isShowError = false;
            }
            if ($isShowError) {
                return $this->tplName . " Is Not Found";
            } else {
                return '';
            }
        }
        self::$rootTpl = $this->tplName;

        $tplType = $this->tplType;

        $setApiConfig = $this->{self::API_CONFIG};
        if (is_function($setApiConfig)) {
            $setApiConfig();
        }

        $this->__apiRunStart($tplType);

        $this->plu = $this->getPluObject();
        // 先运行根目录下的_init.php文件
        if (method_exists(parent::class, '__run')) {
            parent::__run();
        }

        // 在运行当前目录下的_init.php文件
        $di = $this->getDataInit();
        if ($di !== true) {
            return $di;
        }

        if (empty($tplType) || $tplType == 'json') {
            return $this->runJson();
        }

        $grd = $this->getRetData();
        if ($grd === false) {
            $retData = "";
        } elseif (empty($this->exitJson)) {
            list($status, $retData) = $grd;
            if (empty(self::$class[$tpl])) {
                self::$class[$tpl] = [];
            }

            self::$class[$tpl][$this->tplType] = true;
            $arrStatic = $this->config['static'];
            if (!empty($arrStatic) && is_array($arrStatic)) {
                foreach ([$arrStatic[$this->tplType], $arrStatic['#']] as $cStatic) {
                    if (!empty($cStatic) && is_string($cStatic)) {
                        $cStatic = trim($cStatic);
                        if (!empty($cStatic)) {
                            $aStatic = explode(",", $cStatic);
                            foreach ($aStatic as $as) {
                                $as = trim($as);
                                if (empty($as)) {
                                    continue;
                                }
                                self::$class[$tpl][$as] = true;
                            }
                        }
                    }
                }
            }
            
            if ($status) {
                foreach ($this->viewData as $key => $val) {
                    $retData[$key] = $val;
                }

                foreach (self::$viewDataStatic as $key => $val) {
                    !isset($retData[$key]) && $retData[$key] = $val;
                }

                $grd[1] = $retData;
                $view = $this->config['view'];
                if (!empty($view) && is_array($view)) {
                    foreach ($view as $key => $val) {
                        if (is_string($key)) {
                            $retData[$key] = $val;
                        }
                    }
                }
            }
            $tmType = $this->tplMethodType;
            $tmTpl = "{$tpl}.{$tmType}";
            if (file_exists($this->tplPath . "{$tmType}.blade.php")) {
                if ($status) {
                    $argsUrl = $this->config['args']['url'];
                    empty($argsUrl) && $argsUrl = "";
                    $retData['argsUrl'] = $argsUrl;
                    $retData['tpl'] = $this;
                    $retData['plu'] = $this->plu;
                    $retData['layout'] = $this->getViewDir($tmTpl);
                    if (view()->exists($tmTpl)) {
                        $tplView = view($tmTpl, $retData);
                    } else {
                        $tplView = "{$tmTpl} Is Not Found";
                    }
                } else {
                    $tplView = $retData['_'];
                }
                $c = $this->config;
                $tplDelete = $this->isDomainStart;
                if (isset($c['tpldelete']) && is_bool($c['tpldelete'])) {
                    $tplDelete = $c['tpldelete'];
                }
                
                if ((isset($c['layout']) && $c['layout'] === false) || $tplDelete) {
                    $retData = $tplView;
                } else {
                    $class = str_replace(".", "_", $this->class);
                    $class = str_replace("/", "_", $class);
                    $tplName = str_replace("/", "_", $this->tplName);
                    $retData = "<div class=\"{$class}\" tpl=\"{$tplName}\">\r\n{$tplView}\r\n</div>";
                }
            } elseif (!$status) {
                $retData = $retData['_'];
            }

            $tc = $this->config;
            if (is_array($tc) && is_array($tc['plu']) && is_object($tc['plu']['caller']) && is_object($tc['plu']['caller']->plu)) {
                $tcPlu = $tc['plu']['caller']->plu;
                if (is_object($tcPlu) && method_exists($tcPlu, 'tpl')) {
                    $retData = call_user_func_array([$tcPlu, 'tpl'], [&$retData, $tpl]);
                }
            }

            if (is_array($retData)) {
                $retData['src'] = $grd[2];
                if (is_null($retData['src'])) {
                    $retData['src'] = [];
                }
            }

            $getApiData = $this->{self::API_DATA};
            if (is_function($getApiData)) {
                $retData = $getApiData($retData);
            }

            $this->retData = $retData;
            $this->__apiRunEnd($tplType);
            $retData = $this->retData;
        } else {
            $retData = $this->exitJson;
        }

        if ($isArray) {
            $retData = [$retData, $grd[1], [$this->cookies, $this->cookiesForget], $this->exitJson];
        }

        if (method_exists(parent::class, '__last')) {
            parent::__last($retData);
        }
        return $retData;
    }
}
