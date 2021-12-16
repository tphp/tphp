<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Domains;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Response;
use Tphp\Controller;
use Tphp\Basic\Tpl\Handle as TplHandle;
use Tphp\Basic\Tpl\Run;
use Tphp\Basic\Tpl\Init as TplInit;
use Tphp\Basic\Sql\Init as SqlInit;
use Tphp\Register;
use Tphp\Config as TphpConfig;

class DomainsController extends Controller
{

    function __construct($tpl = '', $config = null)
    {
        if (empty(TphpConfig::$domainPath)) {
            TphpConfig::$domainPath = new Path($tpl);
        }

        $dcConfig = TphpConfig::$domain;

        $conn = $dcConfig['conn'];
        if (is_function($conn)) {
            $conn = $conn();
        }
        if (!empty($conn)) {
            config(["database.default" => $conn]);
        }

        $method = TplInit::methodForClass('__initMainConfig', '\MainController', '__initConfig')->auto(false)->onlyStatic();
        if ($method->exists()) {
            $method->invoke(TphpConfig::$domainPath->tplPath);
        }
        // 如果未定义 Register::$tplPath 则须再次定义该变量
        $this->setBaseTplPath(TphpConfig::$domainPath->tplPath);

        $domainPath = TphpConfig::$domainPath;

        $dcConfigPlu = $dcConfig['plu'];
        if (!empty($dcConfigPlu) && !empty($dcConfigPlu['dir'])) {
            $pluDir = $dcConfigPlu['dir'];
            $domainPath->basePluPath = $pluDir;
        }

        foreach ($domainPath as $key => $val) {
            $this->$key = $val;
        }
        $this->config = $config;

        if (is_null($this->isMainPath)) {
            $this->isMainPath = false;
        }
    }

    /**
     * 系统初始化调用模块
     * @param $tplPath
     */
    private function setBaseTplPath($tplPath)
    {
        if (!is_null(Register::$tplPath)) {
            return;
        }
        $tplBase = Register::getHtmlPath(true) . "/";
        $topPath = Register::getTopPath("/");

        $domainPath = TphpConfig::$domainPath;

        if (empty($tplPath)) {
            $tplPath = "index";
        }

        Register::$tplPath = $topPath;
        $domainPath->baseTplPath = $topPath;
        $domainPath->tplPath = $tplPath;
    }

    /**
     * 建立动态CSS或JS
     * @param $html
     * @param array $dfs
     * @param array $style
     * @return string
     */
    private static function obRebuildStatic($html, $dfs = [], $style = [])
    {
        $css = $dfs['css'];
        if (empty($css)) {
            $css = [];
        }
        if (!empty($dfs['css_cache'])) {
            $css[] = TplHandle::getUrl('/static/tpl/css/' . $dfs['css_cache'] . '.css');
        }

        $js = $dfs['js'];
        if (empty($js)) {
            $js = [];
        }
        if (!empty($dfs['js_cache'])) {
            $js[] = TplHandle::getUrl('/static/tpl/js/' . $dfs['js_cache'] . '.js');
        }
        $htmlLower = strtolower($html);
        $htmlLength = strlen($htmlLower);

        $posHeadTop = strpos($htmlLower, '<head');
        if ($posHeadTop === false) {
            $posHeadTop = -1;
        }
        $posHead = strpos($htmlLower, '</head>');
        
        if ($posHead > 0) {
            // 如果在head标签前面存在以下标签则放在以下标签前面
            foreach (['<!--', '<style', '<link', '<script'] as $other) {
                $posOther = strpos($htmlLower, $other);
                if ($posOther > 0 && $posOther > $posHeadTop && $posOther < $posHead) {
                    $posHead = $posOther;
                }
            }
        }

        while ($posHead > 0 && in_array($htmlLower[$posHead - 1], [" ", "\t"])) {
            $posHead --;
        }
        
        $posBody = strrpos($htmlLower, '</body>');
        $tStr = "";
        if ($posHead > 0) {
            $tStr = "\t";
            $htmlLeft = substr($html, 0, $posHead);
            if ($posBody > 0) {
                $htmlMid = substr($html, $posHead, $posBody - $posHead);
                $htmlRight = substr($html, $posBody);
            } else {
                $htmlMid = substr($html, $posHead);
                $htmlRight = "";
            }
        } else {
            $htmlLeft = "";
            if ($posBody > 0) {
                $htmlMid = substr($html, 0, $posBody);
                $htmlRight = substr($html, $posBody);
            } else {
                $htmlMid = $html;
                $htmlRight = "";
            }
        }

        $version = env("VERSION");
        if (empty($version)) {
            $version = "";
        } else {
            $version = "?v={$version}";
        }
        
        $styleInfo = [];
        $styleTop = [];
        $styleDown = [];
        if (!empty($style)) {
            foreach ($style as list($sCode, $isTop)) {
                if ($isTop) {
                    $styleTop[] = $sCode;
                } else {
                    $styleDown[] = $sCode;
                }
            }
        }

        if (!empty($styleTop)) {
            $styleInfo['top'] = $styleTop;
        }
        
        if (!empty($styleDown)) {
            $styleInfo['down'] = $styleDown;
        }
        
        foreach ($styleInfo as $key => $styleVal) {
            $styleArr = [];
            foreach ($styleVal as $s) {
                $styleArr[] = str_replace("\n", "\n\t\t", $s);
            }
            $styleStr = implode("\n\n\t\t", $styleArr);
            $styleInfo[$key] = <<<EOF
    <style>
        {$styleStr}
    </style>

EOF;
        }

        $topStr = "";
        if (!empty($styleInfo['top'])) {
            $topStr .= $styleInfo['top'];
        }
        if (!empty($css)) {
            foreach ($css as $c) {
                $c = str_replace('"', '&quot;', $c);
                $c = ltrim($c, '@');
                if (strpos($c, "?") === false) {
                    $c .= $version;
                }
                $topStr .= $tStr . '<link rel="stylesheet" href="' . $c . '" />' . "\n";
            }
        }

        if (!empty($styleInfo['down'])) {
            $topStr .= $styleInfo['down'];
        }

        if (!empty($dfs['runjs_code_top'])) {
            $runjsCode = str_replace("\n", "\n\t", $dfs['runjs_code_top']);
            $topStr .= "\t{$runjsCode}\n";
        }

        $downStr = "";
        if (!empty($js)) {
            empty($htmlRight) && $downStr .= "\n";
            foreach ($js as $j) {
                $j = str_replace('"', '&quot;', $j);
                if (strpos($j, "?") === false) {
                    $j .= $version;
                }
                if ($j[0] == '@') {
                    $j = ltrim($j, '@');
                    $topStr .= $tStr . '<script src="' . $j . '"></script>' . "\n";
                } else {
                    $downStr .= '<script src="' . $j . '"></script>' . "\n";
                }
            }
        }

        if (!empty($dfs['runjs_code'])) {
            $downStr .= "{$dfs['runjs_code']}\n";
        }

        $html = $htmlLeft . $topStr . $htmlMid . $downStr . $htmlRight;
        return $html;
    }

    /**
     * 修改头部信息SEO设置TDK
     * @param $html
     * @param array $config
     * @return null|string|string[]
     */
    public static function obRebuildSeo($html, $config = [])
    {
        if (!is_array($config)) {
            return $html;
        }
        $htmlLower = strtolower($html);
        $posHeadLeft = strpos($htmlLower, '<head>');
        $posHeadRight = strpos($htmlLower, '</head>');

        //如果页面本来就没有head头，则直接返回
        if ($posHeadLeft === false || $posHeadRight === false || $posHeadLeft >= $posHeadRight) {
            return $html;
        }

        $flagK = "_##k##_"; //替换关键词标识
        $flagD = "_##d##_"; //替换描述标识

        $posHeadLeft += 6;
        $htmlLeft = substr($html, 0, $posHeadLeft);
        $htmlRight = substr($html, $posHeadRight);

        foreach ($config as $key => $val) {
            if ($key == 'title') {
                continue;
            }
            $config[$key] = str_replace("'", "&#39;", $val);
        }

        $head = substr($html, $posHeadLeft, $posHeadRight - $posHeadLeft);
        if (!empty($config['title'])) {
            $title = preg_replace("/<[^>]*>/is", "", $config['title']);
            $title = "<title>{$title}</title>";
            if (preg_match('!<title>.*?</title>!', $head)) {
                $head = preg_replace("!<title>.*?</title>!ui", $title, $head, 1);
            } else {
                $head = "\n\t" . $title . $head;
            }
        }

        if (!empty($config['keywords'])) {
            $keywords = "<meta name='keywords' content='{$config['keywords']}' />";
            if (preg_match("!<meta\s.*?name=['\"]keywords!ui", $head)) {
                $head = preg_replace("!<meta\s.*?name=['\"]keywords.*?/?>!ui", $flagK, $head, 1);
            } else {
                $keywords = "\n\t{$keywords}";
                $head = preg_replace("!</title>!ui", '</title>' . $flagK, $head, 1);
            }
        }

        if (!empty($config['description'])) {
            $description = "<meta name='description' content='{$config['description']}' />";
            if (preg_match("!<meta\s.*?name=['\"]description!ui", $head)) {
                $head = preg_replace("!<meta\s.*?name=['\"]description.*?/?>!ui", $flagD, $head, 1);
            } else {
                $description = "\n\t{$description}";
                $head = preg_replace("!" . $flagK . "!ui", $flagK . $flagD, $head, 1);
            }
        }

        if (!empty($config['keywords'])) $head = preg_replace("!" . $flagK . "!ui", $keywords, $head, 1);
        if (!empty($config['description'])) $head = preg_replace("!" . $flagD . "!ui", $description, $head, 1);
        return $htmlLeft . $head .$htmlRight;
    }

    /**
     * 返回JSON页面格式
     * @param $html
     * @return array
     */
    private static function obExitJson($html)
    {
        $htmlDe = json_decode($html, true);
        if ((empty($htmlDe) && !is_array($htmlDe)) || Register::$isDump) {
            return [false, $html];
        } else {
            try {
                header('Content-Type:application/json; charset=utf-8');
            } catch (Exception $e) {
                // TODO
            }
            return [true, json_encode($htmlDe, JSON_UNESCAPED_UNICODE)];
        }
    }

    /**
     * 设置 http 头
     * @param $header
     */
    private static function obRebuildHeader($header)
    {
        foreach ($header as $key => $val) {
            try {
                header("{$key}:{$val}");
            } catch (Exception $e) {
                // TODO
            }
        }
    }

    /**
     * 载入加载模块
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function tpl()
    {
        ob_start('self::runObStartHtml');
        $this->isRebuildHtml = false;
        list($html, list($cookies, $cookiesForget)) = $this->_tpl_($this->tplPath, $this->tplType);
        $retRp = Response::make($html);
        if (is_array($cookies)) {
            //cookies设置
            foreach ($cookies as $val) {
                list($k, $v, $exp) = $val;
                if ($exp <= 0) {
                    $cookie = Cookie::forever($k, $v);
                } else {
                    $cookie = Cookie::make($k, $v, $exp);
                }
                $retRp->withCookie($cookie);
            }
            //cookies删除
            foreach ($cookiesForget as $val) {
                $retRp->withCookie(Cookie::forget($val));
            }
        }
        if ($this->isRebuildHtml) {
            TplHandle::loadStatic();
        }
        TplInit::runMethodAuto($this->tplType, '', $this->obj);
        self::runObStart();
        return $retRp;
    }

    /**
     * 设置动态运行JS任务
     * @return bool
     */
    private static function setRunJsInit()
    {
        $imports = get_ob_start_value('imports');
        if (empty($imports)) {
            return false;
        }

        $runJs = $imports['runjs'];
        if (empty($runJs)) {
            return false;
        }

        $js = $imports['js'];
        empty($js) && $js = [];
        $resets = [];
        foreach ($runJs as $pluDir => $dirs) {
            if (isset($js[$pluDir])) {
                foreach ($dirs as $dir => $val) {
                    if (!isset($js[$pluDir][$dir])) {
                        !isset($resets[$pluDir]) && $resets[$pluDir] = [];
                        $resets[$pluDir][] = $dir;
                    }
                }
            } else {
                foreach ($dirs as $dir => $val) {
                    !isset($resets[$pluDir]) && $resets[$pluDir] = [];
                    $resets[$pluDir][] = $dir;
                }
            }
        }

        if (empty($resets)) {
            return false;
        }
        foreach ($resets as $pluDir => $dirs) {
            foreach ($dirs as $dir) {
                TplHandle::setPluginsStaticPaths($pluDir, $dir, 'js', true);
            }
        }
        return true;
    }

    /**
     * 设置动态运行JS缓存
     */
    private static function __setRunJsCache($loads, $exists, $type)
    {
        if (empty($loads)) {
            return false;
        }

        if (empty($exists)) {
            $exists = [];
        }

        $existLoads = [];
        foreach ($loads as $key => $val) {
            $es = $exists[$key];
            if (empty($es)) {
                $es = [];
            }
            $newVal = [];
            foreach ($val as $k => $v) {
                if (!isset($es[$k]) || is_array($es[$k])) {
                    $newVal[$k] = $v;
                }
            }
            if (!empty($newVal)) {
                $existLoads[$key] = $newVal;
            }
        }

        if (empty($existLoads)) {
            return false;
        }

        $loadsSrc = $existLoads;
        ksort($existLoads);
        $md5s = [];
        foreach ($existLoads as $key => $val) {
            $addI = 0;
            $md5 = "";
            $str = "";
            ksort($val);
            foreach ($val as $k => $v) {
                $str .= "#" . $k;
                if ($addI > 5) {
                    $addI = 0;
                    $md5 = md5($md5 . $str);
                    $str = "";
                } else {
                    $addI++;
                }
            }
            !empty($str) && $md5 = md5($md5 . $str);
            $md5 = substr($md5, 8, 16);
            $md5s[] = $md5;
        }
        $md5Str = substr(md5(implode("#", $md5s)), 8, 16);
        $runCaches = [];
        foreach ($loadsSrc as $key => $val) {
            $runCaches[$key] = array_keys($val);
        }
        \Illuminate\Support\Facades\Cache::put("run{$type}_{$md5Str}", $runCaches, TplInit::$cacheTime);
        set_ob_start_value([ "run{$type}_md5" => $md5Str ], 'static');
    }

    /**
     * 设置动态运行JS缓存
     */
    private static function setRunJsCache()
    {
        $imports = get_ob_start_value('imports');
        if (empty($imports)) {
            return false;
        }
        $runJsLoads = $imports['runjs_loads'];
        if (!empty($runJsLoads)) {
            self::__setRunJsCache($runJsLoads, $imports['js'], 'js');
        }
        $runCssLoads = $imports['runcss_loads'];
        if (!empty($runCssLoads)) {
            self::__setRunJsCache($runCssLoads, $imports['scss'], 'css');
        }
    }

    /**
     * 转换参数中的函数调用
     * @param $args
     */
    private static function changeArgsFunction(&$args)
    {
        while (is_function($args)) {
            $args = $args();
        }

        if (empty($args) || (!is_array($args) && !is_object($args))) {
            return;
        }
        if (is_object($args)) {
            try{
                $args = json_decode(json_encode($args, JSON_UNESCAPED_UNICODE), true);
            } catch (\Exception $e) {
                $args = 'null';
                return;
            }
            if (empty($args) || !is_array($args)) {
                return;
            }
        }
        foreach ($args as $key => $val) {
            if (is_array($val) || is_object($val) || is_function($val)) {
                self::changeArgsFunction($args[$key]);
            }
        }
    }

    /**
     * 设置动态运行JS生成代码
     * @param string $scriptStr
     * @return bool
     */
    private static function setRunJsCode($scriptStr = '')
    {
        $imports = get_ob_start_value('imports');
        if (empty($imports)) {
            return false;
        }
        $runJsIds = $imports['runJsIds'];
        if (empty($runJsIds)) {
            return false;
        }
        $js = $imports['js'];
        empty($js) && $js = [];

        $newIds = [];
        $f = 'function';
        $fLen = strlen($f);
        $funs = [];
        foreach ($runJsIds as $id => $info) {
            $fun = $info[0];
            if (empty($fun)) {
                if (count($info) !== 4) {
                    continue;
                }
                $pluDir = $info[2];
                $dir = $info[3];
                if (!isset($js[$info[2]]) || !isset($js[$pluDir][$dir])) {
                    continue;
                }
                $funId = "{$pluDir}#{$dir}";
                $fun = $funs[$funId];
                if (!isset($fun)) {
                    $jsArr = $js[$pluDir][$dir];
                    $jsList = [];
                    foreach ($jsArr as $ja) {
                        if (is_array($ja)) {
                            $ja = $ja[0];
                        }

                        if (is_string($ja)) {
                            $ja = trim($ja);
                            if (!empty($ja)) {
                                $jsList[] = $ja;
                            }
                        }
                    }

                    if (empty($jsList)) {
                        $funs[$funId] = '';
                        continue;
                    }

                    $jsStr = implode("\n", $jsList);
                    
                    $pos = strpos($jsStr, $f);
                    if ($pos === false) {
                        $funs[$funId] = '';
                        continue;
                    }
                    $jsStr = substr($jsStr, $pos + $fLen);
                    $pos = strpos($jsStr, '(');
                    if ($pos === false) {
                        $funs[$funId] = '';
                        continue;
                    }
                    $fun = trim(substr($jsStr, 0, $pos));
                    if (empty($fun)) {
                        $funs[$funId] = '';
                        continue;
                    }
                    $funs[$funId] = $fun;
                }
                if (empty($fun)) {
                    continue;
                }
            }

            // 方法验证，必须由字母数字或下划线组成，并且首字母不能为数字
            if (preg_match('/^[^0-9]\w+$/', $fun)) {
                $newIds[$id] = [$fun, $info[1]];
            }
        }
        if (empty($newIds)) {
            return false;
        }

        $jsCode = <<<EOF
    [
        #ARGS#
    ]
EOF;

        $jsLoops = [];
        $sep = "\n        , ";
        foreach ($newIds as $id => list($fun, $args)) {
            if ($id[0] === '#') {
                $idName = ltrim($id, '#');
            } else {
                $idName = "run_{$id}";
            }
            $newArgs = ["'{$idName}'", "'{$fun}'"];
            foreach ($args as $arg) {
                self::changeArgsFunction($arg);
                if (is_null($arg)) {
                    $value = 'null';
                } elseif (is_string($arg)) {
                    $arg = str_replace("'", "\\'", $arg);
                    $value = "'{$arg}'";
                } elseif (is_object($arg) || is_array($arg)) {
                    try{
                        $value = json_encode($arg, JSON_UNESCAPED_UNICODE);
                        if (is_null($value)) {
                            $value = 'null';
                        }
                    } catch (\Exception $e) {
                        $value = 'null';
                    }
                } elseif (is_bool($arg)) {
                    if ($arg) {
                        $value = 'true';
                    } else {
                        $value = 'false';
                    }
                } elseif (is_numeric($arg)) {
                    $value = $arg;
                } else {
                    $value = 'null';
                }
                $newArgs[] = $value;
            }
            $tJs = str_replace('#ARGS#', implode($sep, $newArgs), $jsCode);
            $jsLoops[] = $tJs;
        }

        $jsLoopsStr = implode(",\n", $jsLoops);
        if (!empty($scriptStr)) {
            $scriptStr = "\n\n{$scriptStr}";
        }

        $jsScript = <<<EOF
<script>
var runjs = [
{$jsLoopsStr}
];
for (var i in runjs) {
    var rj = runjs[i], fn = null, args = [rj[0]];
    try{ fn = eval(rj[1]); } catch (e) { continue; }
    for (var j = 2; j < rj.length; j ++) { args.push(rj[j]); }
    fn.apply(this, args);
}{$scriptStr}
</script>
EOF;
;
        set_ob_start_value([ 'runjs_code' => $jsScript ], 'static');
        return true;
    }

    /**
     * 静态文件去重 JS 或 CSS
     * @param $statics
     * @return array
     */
    private static function staticUnique($statics)
    {
        $retKv = [];
        foreach ($statics as $static) {
            $type = 0;
            if ($static[0] == '@') {
                if ($static[1] == '@') {
                    $type = 2;
                } else {
                    $type = 1;
                }
                $static = ltrim($static, '@');
            }
            if (isset($retKv[$static])) {
                if ($retKv[$static] < $type) {
                    $retKv[$static] = $type;
                }
            } else {
                $retKv[$static] = $type;
            }
        }
        $ret = [];
        $bottoms = [];
        
        foreach ($retKv as $key => $val) {
            if ($val == 0) {
                $bottoms[] = $key;
            } else {
                $ret[] = str_repeat('@', $val) . $key;
            }
        }

        foreach ($bottoms as $bt) {
            $ret[] = $bt;
        }

        return $ret;
    }

    /**
     * 代码执行后渲染
     */
    public static function runObStart()
    {
        // 初始化JS
        self::setRunJsInit();

        // 设置JS缓存
        self::setRunJsCache();

        // 生成JS页面代码
        $scriptStr = '';
        $scriptList = get_ob_start_value('script');
        $script = [];
        $scriptTop = [];
        if (!empty($scriptList)) {
            foreach ($scriptList as list($sCode, $isTop)) {
                if ($isTop) {
                    $scriptTop[] = $sCode;
                } else {
                    $script[] = $sCode;
                }
            }
        }
        if (!empty($script)) {
            $scriptStr = implode("\n\n", $script);
            $scriptStr = rtrim($scriptStr);
        }

        if (!self::setRunJsCode($scriptStr) && !empty($scriptStr)) {
            set_ob_start_value([ 'runjs_code' => <<<EOF
<script>
{$scriptStr}
</script>
EOF
            ], 'static');
        }

        $scriptStr = '';
        if (!empty($scriptTop)) {
            $scriptStr = implode("\n\n", $scriptTop);
            $scriptStr = rtrim($scriptStr);
        }
        if (!empty($scriptStr)) {
            set_ob_start_value([ 'runjs_code_top' => <<<EOF
<script>
{$scriptStr}
</script>
EOF
            ], 'static');
        }

        // 运行JS缓存
        $osv = get_ob_start_value();
        $statics = $osv['statics'];
        list($csss, $jss) = self::runObStartStatic($statics);
        $static = $osv['static'];
        if (!empty($static)) {
            $cssMd5 = $static['css_md5'];
            $runCssMd5 = $static['runcss_md5'];
            if (!empty($cssMd5) || !empty($runCssMd5)) {
                $cssCacheStr = "{$cssMd5}#{$runCssMd5}";
                $cssCache = substr(md5($cssCacheStr), 8, 16);
                \Illuminate\Support\Facades\Cache::put("css_cache_{$cssCache}", $cssCacheStr, TplInit::$cacheTime);
                TphpConfig::$obStart['static']['css_cache'] = $cssCache;
            }

            $jsMd5 = $static['js_md5'];
            $runJsMd5 = $static['runjs_md5'];
            if (!empty($jsMd5) || !empty($runJsMd5)) {
                $jsCacheStr = "{$jsMd5}#{$runJsMd5}";
                $jsCache = substr(md5($jsCacheStr), 8, 16);
                \Illuminate\Support\Facades\Cache::put("js_cache_{$jsCache}", $jsCacheStr, TplInit::$cacheTime);
                TphpConfig::$obStart['static']['js_cache'] = $jsCache;
            }

            $staticCss = $static['css'];
            empty($staticCss) && $staticCss = [];
            if (!empty($csss)) {
                foreach ($csss as $css) {
                    if (!in_array($css, $staticCss)) {
                        $staticCss[] = $css;
                    }
                }
            }
            if (!empty($staticCss)) {
                TphpConfig::$obStart['static']['css'] = self::staticUnique($staticCss);
            }

            $staticJs = $static['js'];
            empty($staticJs) && $staticJs = [];
            if (!empty($jss)) {
                foreach ($jss as $js) {
                    if (!in_array($js, $staticJs)) {
                        $staticJs[] = $js;
                    }
                }
            }
            if (!empty($staticJs)) {
                TphpConfig::$obStart['static']['js'] = self::staticUnique($staticJs);
            }
        }
        // 自定义方法
        $function = $osv['function'];
        if (!empty($function)) {
            $before = [];
            $after = [];
            foreach ($function as $key => list($isBefore, $fun)) {
                if ($isBefore) {
                    $before[$key] = $fun;
                } else {
                    $after[$key] = $fun;
                }
            }
            $funs = [];
            if (!empty($before)) {
                $funs['before'] = array_values($before);
            }
            if (!empty($after)) {
                $funs['after'] = array_values($after);
            }
            TphpConfig::$obStart['function'] = $funs;
        }

        if (isset(TphpConfig::$obStart['header'])) {
            self::obRebuildHeader(TphpConfig::$obStart['header']);
        }
    }

    /**
     * 获取动态 CSS 和 JS
     * @param $statics
     * @return array
     */
    public static function runObStartStatic($statics)
    {
        $csss = [];
        $jss = [];
        if (empty($statics)) {
            return [$csss, $jss];
        }
        $_url = TplHandle::getUrl('');
        foreach ($statics as $key => $val) {
            $basePath = "{$_url}/static/plugins/{$key}";
            foreach ($val as $k => $v) {
                if (!$v) {
                    continue;
                }
                $isContinue = false;
                $isTop = true;
                if ($k[0] == '#') {
                    $isTop = false;
                }
                $k = ltrim($k, "#@");
                if ($k[0] === '/') {
                    $url = TplHandle::getUrl($k);
                    $isContinue = true;
                } elseif (strrpos($k, "://") !== false) {
                    $url = $k;
                    $isContinue = true;
                }

                $bPath = $basePath;
                if (!$isContinue) {
                    $pos = strpos($k, "=");
                    if ($pos > 0) {
                        $pluDir = trim(substr($k, $pos), "=");
                        $pluDir = str_replace("\\", "/", $pluDir);
                        $pluDir = str_replace(".", "/", $pluDir);
                        $pluDir = trim($pluDir, "/");
                        $pluDirArr = explode("/", $pluDir);
                        if (count($pluDirArr) !== 2) {
                            continue;
                        }
                        list($top, $sub) = $pluDirArr;
                        $top = trim($top);
                        $sub = trim($sub);
                        if (empty($sub) || empty($sub)) {
                            continue;
                        }

                        $bPath = "{$_url}/static/plugins/{$top}/{$sub}";
                        $k = substr($k, 0, $pos);
                    }
                }

                $isSet = false;
                $pos = strrpos($k, ".");
                $ext = "";
                if ($pos > 0) {
                    $ext = strtolower(substr($k, $pos + 1));
                    if (in_array($ext, ['scss', 'css', 'js'])) {
                        $isSet = true;
                    }
                }
                if (!$isSet) {
                    $pos = strrpos($k, "|");
                    if ($pos > 0) {
                        $ext = strtolower(substr($k, $pos + 1));
                        if (in_array($ext, ['scss', 'css', 'js'])) {
                            $isSet = true;
                            $k = substr($k, 0, $pos);
                        }
                    }
                }

                if (!$isSet) {
                    continue;
                }

                if (!$isContinue) {
                    $url = "{$bPath}/{$k}";
                }

                if ($isTop) {
                    $url = "@@{$url}";
                }

                if ($ext === 'js') {
                    $jss[] = $url;
                } else {
                    $csss[] = $url;
                }
            }
        }
        $csss = array_unique($csss);
        $jss = array_unique($jss);
        return [$csss, $jss];
    }

    /**
     * 自定义方法调用
     * @param $functions
     * @param $html
     * @return string
     */
    private static function runObStartFunctions($functions, $html)
    {
        if (empty($functions)) {
            return $html;
        }

        foreach ($functions as $function) {
            try{
                $h = $function($html);
                if (is_null($h)) {
                    continue;
                }
                if (is_array($h)) {
                    $h = json_encode($h, JSON_UNESCAPED_UNICODE);
                } elseif (is_numeric($h)) {
                    $h .= "";
                } elseif (is_bool($h)) {
                    if ($h) {
                        $h = 'true';
                    } else {
                        $h = 'false';
                    }
                } elseif (!is_string($h)) {
                    continue;
                }
                $html = $h;
            } catch (\Exception $e) {
                // TODO
            }
        }

        return $html;
    }

    /**
     * 代码执行后渲染(重构)
     */
    public static function runObStartHtml($html)
    {
        $osv = get_ob_start_value();
        if (empty($osv)) {
            list($isJson, $html) = self::obExitJson($html);
            return $html;
        }
        $before = [];
        $after = [];
        $function = $osv['function'];
        if (!empty($function)) {
            !empty($function['before']) && $before = $function['before'];
            !empty($function['after']) && $after = $function['after'];
        }

        $html = self::runObStartFunctions($before, $html);

        list($isJson, $html) = self::obExitJson($html);

        if (isset($osv['header'])) {
            self::obRebuildHeader($osv['header']);
        }
        
        if ($isJson) {
            $html = self::runObStartFunctions($after, $html);
            return $html;
        }

        if (isset($osv['seo'])) {
            $html = self::obRebuildSeo($html, $osv['seo']);
        }

        if (count($_POST) <= 0) {
            if (!empty($osv['static']) || !empty($osv['style'])) {
                $html = self::obRebuildStatic($html, $osv['static'], $osv['style']);
            }
        }

        $html = self::runObStartFunctions($after, $html);

        return $html;
    }

    //数据库操作
    public function db($table = "", $conn = "")
    {
        if (empty($conn)) {
            $conn = config('database.default');
        }

        list($table, $conn) = SqlInit::__init()->getPluginTable($table, $conn);

        if (empty($table)) {
            $db = \DB::connection($conn);
        } else {
            $db = \DB::connection($conn)->table($table);
        }
        return $db;
    }

    /**
     * 获取默认plu插件
     * @return Basic\Plugin\PluginClass
     */
    protected function getDefaultPlugins($dc, $obj = null)
    {
        $dir = '';
        if (isset($dc['plu']) && isset($dc['plu']['dir'])) {
            $dir = $dc['plu']['dir'];
            if (is_string($dir)) {
                $dir = trim($dir);
            } else {
                $dir = '';
            }
        }
        $pluObj = plu($dir, $obj);
        return $pluObj;
    }

    /**
     * 如果设置 url 则直接跳转
     * @param $goUrl 跳转路径
     * @param $goPostMessage POST 提交信息
     */
    public function gotoUrl($goUrl, $goPostMessage)
    {
        if (empty($goUrl) || !is_string($goUrl)) {
            return;
        }

        $goUrl = trim($goUrl);
        $goUrl = str_replace("\\", "/", $goUrl);
        $goUrl = trim($goUrl, "/");
        if (empty($goUrl)) {
            return;
        }

        if (strpos($goUrl, "://") === false) {
            $fullUrl = TplHandle::getFullUrl(true);
            $goUrl = "/{$goUrl}";
        } else {
            $fullUrl = TplHandle::getFullUrl();
            $goUrlLower = strtolower($goUrl);
            if (strpos($goUrlLower, "http://") === 0) {
                $goUrl = str_replace(":80", "", $goUrl);
            } elseif (strpos($goUrlLower, "https://") === 0) {
                $goUrl = str_replace(":443", "", $goUrl);
            }
        }
        // 如果路径和当前相同则不跳转
        if ($fullUrl === $goUrl) {
            return;
        }

        if (count($_POST) > 0) {
            if (empty($goPostMessage)) {
                $href = str_replace('"', '\\"', $goUrl);
                $goPostMessage = "Redirect: <a href=\"{$href}\" target=\"_blank\">{$goUrl}</a>";
            }
            EXITJSON(0, $goPostMessage);
        } else {
            redirect($goUrl)->send();
        }

    }

    /**
     * 获取文件
     * @param $file
     * @return bool|mixed
     */
    private function includeFile($file)
    {
        if (file_exists($file)) {
            return include $file;
        }

        return null;
    }

    /**
     * 获取模板文件
     * @param string $tpl
     * @param string $type
     * @return array
     */
    protected function _tpl_($tpl = '', $type = 'html')
    {
        // 首先在data.php中取得layout,其次是domain.php中选定
        $dc = &TphpConfig::$domain;
        // 如果设置 url 则直接跳转
        $this->gotoUrl($dc['go'], $dc['go_post_message']);

        $config = $this->config;
        if (empty($config)) {
            $config = [];
        }
        $argsInfo = $this->args;
        !empty($argsInfo) && $config['args'] = $argsInfo;
        $thisPath = Register::getViewPath($this->baseTplPath . $this->tplPath);
        $sqlInit = SqlInit::__init();
        if (!empty($dc['conn']) && !is_string($dc['conn'])) {
            $dc['conn'] = $sqlInit->getConnectionName($dc['conn']);
        }

        $dataFile = $thisPath . '/data.php';
        if (file_exists($dataFile)) {
            $data = $this->includeFile($dataFile);
            (empty($data) || !is_array($data)) && $data = [];
            TplInit::setPluginsConfig($data);
            TphpConfig::$dataFileInfo[$dataFile] = $data;
        } else {
            $data = [];
        }

        if ($type === '#') {
            if (!empty($data['method']) && is_string($data['method'])) {
                $type = trim($data['method']);
            }
            if ((empty($type) && $type != '0') || $type === '#') {
                $type = 'html';
            }
            $this->tplType = $type;
            TphpConfig::$domainPath->tplType = $type;
        }

        $tplFull = $tpl . "." . $type;

        $pluConfig = TplHandle::getPluginsConfig($dc['plu'], $data['plu']);
        TplHandle::runPluginsConfig($pluConfig);

        $method = TplInit::methodForClass('__initMainGetData', '\MainController', '__data')->auto(false)->onlyStatic();
        if ($method->exists()) {
            $invokeData = $method->invokePointer($data, $type, $this->config);
            if (!empty($invokeData) && is_array($invokeData)) {
                $data = $invokeData;
            }
        }

        if (isset($data['layout'])) {
            $layout = $data['layout'];
        } else if (isset($dc['layout'])) {
            $layout = $dc['layout'];
            !isset($config['layout']) && $config['layout'] = $layout;
        } else {
            $layout = "";
        }

        $seo = [];
        // 标题
        if (isset($data['title'])) {
            $title = $data['title'];
        } elseif (isset($dc['title'])) {
            $title = $dc['title'];
        }
        isset($title) && $seo['title'] = $title;

        // 关键词
        if (isset($data['keywords'])) {
            $keywords = $data['keywords'];
        } elseif (isset($dc['keywords'])) {
            $keywords = $dc['keywords'];
        }
        isset($keywords) && $seo['keywords'] = $keywords;

        // 描述
        if (isset($data['description'])) {
            $description = $data['description'];
        } elseif (isset($dc['description'])) {
            $description = $dc['description'];
        }
        isset($description) && $seo['description'] = $description;
        if (!empty($seo)) {
            set_ob_start_value($seo, 'seo');
        }

        $pluObj = null;

        // TPL 节点
        $list = tpl($tplFull, $config, true);
        $argsUrl = $argsInfo['url'];
        empty($argsUrl) && $argsUrl = "";

        if (!is_array($list)) {
            if (is_string($list)) {
                $obj = TphpConfig::$tpl;
                if (isset($data['plu']) && is_object($data['plu']['caller'])) {
                    $pluObj = self::getProperty($data['plu']['caller'], 'plu');
                    if (is_object($pluObj)) {
                        $pluObj->setTpl($obj);
                    }
                }
                if (empty($pluObj)) {
                    $pluObj = $this->getDefaultPlugins($dc, $obj);
                }
                Run::abort(404, [
                    'plu' => $pluObj,
                    'tpl' => $obj,
                    'message' => $list,
                    'tplType' => $this->tplType,
                    'tplPath' => $this->tplPath,
                    'tplBase' => $this->baseTplPath,
                    'argsUrl' => $argsUrl
                ]);
            }
            return $list;
        }

        if (count($list) == 2) {
            list($lStatus, $lList) = $list;
            if (is_bool($lStatus)) {
                if (is_array($lList)) {
                    $lListStr = json_encode($lList, JSON_UNESCAPED_UNICODE);
                } else {
                    $lListStr = $lList;
                }

                if (!$lStatus) {
                    if (count($_POST)) {
                        EXITJSON(0, $lList);
                    } else {
                        __exit($lListStr);
                    }
                }

                return [$lListStr, []];
            }
        }

        list($__tpl__, $retData, $cookiesInfo, $exitJson, $obj) = $list;
        if (!empty($exitJson)) {
            return [$exitJson, $cookiesInfo];
        }
        if (empty($obj)) {
            $obj = TphpConfig::$tpl;
        }
        $oconf = $obj->config;
        if (isset($oconf['layout'])) {
            $layout = $oconf['layout'];
        }

        $objLayout = $obj->layout();
        if (!is_null($objLayout)) {
            $layout = $objLayout;
        }

        if (isset($data['plu']) && is_object($data['plu']['caller'])) {
            $pluObj = TplInit::getProperty($data['plu']['caller'], 'plu');
            if (is_object($pluObj)) {
                $pluObj->setTpl($obj);
            }
        }
        $this->obj = $obj;
        $type = $obj->tplType;
        if ($layout !== false) {
            $layout = trim($layout);
            $layoutBool = false;
            if (empty($pluObj)) {
                $pluObj = $this->getDefaultPlugins($dc, $obj);
            }

            // 判断是否为主目录下
            if ($this->isMainPath) {
                $dpPlu = TphpConfig::$domainPath->plu;
                if (empty($dpPlu)) {
                    $tplPlu = $pluObj;
                } else {
                    $tplPlu = $dpPlu;
                }
            } else {
                $tplPlu = $pluObj;
            }

            // 插件内查找
            if ($layout[0] === ':') {
                $layout = trim($layout, ':');
                if (!empty($layout)) {
                    $_layout = $tplPlu->view($layout, [], true, true);
                    $layoutDir = str_replace(".", "/", $layout);
                    $layout = $_layout;
                    if (!empty($_layout)) {
                        $layoutBool = true;
                        $layoutFullDir = "/" . $tplPlu->getBasePath("view/" . $layoutDir);

                        // 加载相同路径的scss或js文件
                        foreach (['scss', 'js'] as $typeName) {
                            $isFile = false;
                            $tFile = Register::getViewPath($layoutFullDir . "." . $typeName, false);
                            if (!is_file($tFile)) {
                                continue;
                            }
                            TplHandle::setPluginsStaticPaths($tplPlu->dir, $layoutDir, $typeName, true);
                        }
                    }
                }
                if (!$layoutBool) {
                    $layout = ':';
                }
            }

            if (!$layoutBool) {
                $layouts = [];
                $isRoot = false;
                if (strlen($layout) > 0 && $layout[0] == '/') {
                    $layout = trim($layout, "/");
                    $isRoot = true;
                }
                if (!empty($this->tplPath)) {
                    $layouts[] = [$this->baseTplPath . "layout/" . $this->tplPath, true];
                }
                if (empty($layout)) {
                    // 如果布局为空则使用默认公共的模板
                    $layouts[] = [$this->baseTplPath . "layout/public", true];
                    $layouts[] = ["layout/public", false];
                } elseif($layout === ':') {
                    // 错误插件模板
                    $layouts[] = [$this->baseTplPath . "layout/error", true];
                    $layouts[] = ["layout/error",  false];
                } else {
                    // 已设置的模板
                    if ($isRoot) {
                        $layouts[] = [$layout, false];
                    }
                    $layouts[] = [$this->baseTplPath . "layout/" . $layout, true];
                    $layouts[] = ["layout/{$layout}", false];
                }

                $layoutIndex = null;
                foreach ($layouts as $index => list($val)) {
                    if (view()->exists($val)) {
                        $layout = $val;
                        $layoutIndex = $index;
                        $layoutBool = true;
                        break;
                    } else {
                        $valTpl = "{$val}/tpl";
                        if (view()->exists($valTpl)) {
                            $layout = $valTpl;
                            $layoutIndex = $index;
                            $layoutBool = true;
                            break;
                        }
                    }
                }

                if ($layoutBool) {
                    $layoutDir = str_replace(".", "/", $layout);
                    $layoutDir = str_replace("\\", "/", $layoutDir);
                    $pos = strrpos($layoutDir, "/");
                    if ($pos >= 0) {
                        $layoutTop = substr($layoutDir, 0, $pos);
                        $layoutFile = substr($layoutDir, $pos + 1);
                        TplInit::$class[$layoutTop][$layoutFile] = true;
                    }
                }
            }

            if ($layoutBool) {
                is_array($__tpl__) && $__tpl__ = "";
                $retData['__tpl__'] = $__tpl__;
                $retData['tplType'] = $this->tplType;
                $retData['tplPath'] = $this->tplPath;
                $retData['tplBase'] = $this->baseTplPath;
                $retData['_DC_'] = TphpConfig::$domain;
                $retData['argsUrl'] = $argsUrl;
                $ov = $obj->viewData;
                if (!empty($ov) && is_array($ov)) {
                    foreach ($ov as $key => $val) {
                        !isset($retData[$key]) && $retData[$key] = $val;
                    }
                }
                $this->isRebuildHtml = true;
                $retData['tpl'] = $obj;
                $retData['plu'] = $tplPlu;
                $retData['layout'] = $layout;
                return [view($layout, $retData), $cookiesInfo];
            }
        }
        if (is_array($__tpl__)) {
            if (is_array($__tpl__['_'])) {
                if (!empty($__tpl__['pageinfo'])) {
                    $__tpl__ = [
                        'page' => $__tpl__['pageinfo'],
                        'list' => $__tpl__['_']
                    ];
                } else {
                    $__tpl__ = $__tpl__['_'];
                }
            }
            $__tpl__ = json_encode($__tpl__, true);
        } else {
            $this->isRebuildHtml = true;
        }
        return [$__tpl__, $cookiesInfo];
    }
}
