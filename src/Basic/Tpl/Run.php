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


class Run
{
    private static $browser = null;
    private static $browserVersion = null;

    /**
     * 类公共函数帮助文档
     * @param null $class
     * @param string $find
     * @param int $type: 0:class path, 1:args, 2:namespace, 3:path
     * @return array
     * @throws \ReflectionException
     */
    public static function help($class = null, $find = '', $type = 1)
    {
        $ret = [];
        if (empty($class) || !is_object($class)) {
            return $ret;
        }

        $basePathLen = strlen(base_path());

        $classRef = new \ReflectionClass($class);
        if ($type == 0) {
            $str = substr($classRef->getFileName(), $basePathLen);
            $str = substr($str, 0, strlen($str) - 4);
            return [
                'file' => $str,
                'namespace' => $classRef->getNamespaceName()
            ];
        }

        $isFind = false;
        if (!empty($find) && is_string($find)) {
            $find = strtolower(trim($find));
            if (!empty($find)) {
                $isFind = true;
            }
        }

        $methods = $classRef->getMethods();
        foreach ($methods as $method) {
            if (!$method->isPublic()) {
                continue;
            }

            $methodName = $method->getName();
            if ($isFind) {
                $methodNameLower = strtolower($methodName);
                if (strpos($methodNameLower, $find) === false) {
                    continue;
                }
            }

            $str = "";
            if ($type == 2) {
                $str = $method->class;
            } elseif ($type == 3) {
                $str = substr($method->getFileName(), $basePathLen);
                $str = substr($str, 0, strlen($str) - 4);
            } else {
                $params = [];
                $parameters = $method->getParameters();
                foreach ($parameters as $pt) {
                    $params[] = $pt->getName();
                }
                $str = implode(", ", $params);
            }

            $ret[$methodName] = $str;
        }

        ksort($ret);

        return $ret;
    }

    /**
     * 设置 自定义 ob_start
     * @param null $function 调用函数，为 function 匿名函数
     * @param string $keyName 唯一键值
     * @param bool $isBefore 是否在其他重构之前
     */
    public static function setObStart($function = null, $keyName = '', $isBefore = false)
    {
        if (!is_function($function)) {
            return;
        }
        if (is_string($keyName)) {
            $keyName = trim($keyName);
        } else {
            $keyName = null;
        }

        if (empty($keyName)) {
            $functions = self::getObStartValue('function');
            if (is_array($functions)) {
                $cot = count($functions) + 1;
            } else {
                $cot = 1;
            }
            $keyName = "_#{$cot}#_";
        }

        self::setObStartValue([ $keyName => [$isBefore, $function] ], 'function');
    }

    /**
     * 设置ob_start信息
     * @param null $config
     * @param string $key
     * @param bool $isOverflow
     */
    public static function setObStartValue($config = null, $key = '', $isOverflow = false)
    {
        if (empty($config) || (!empty($key) && !is_string($key))) {
            return;
        }
        if (empty(TphpConfig::$obStart)) {
            TphpConfig::$obStart = [];
        }

        if ($isOverflow) {
            if (empty($key)) {
                TphpConfig::$obStart = $config;
            } else {
                TphpConfig::$obStart[$key] = $config;
            }
            return;
        }

        if (empty($key)) {
            if (empty($config) || !is_array($config)) {
                return;
            }
            foreach ($config as $k => $v) {
                TphpConfig::$obStart[$k] = $v;
            }
            return;
        }

        if (is_array($config)) {
            if (!is_array(TphpConfig::$obStart[$key])) {
                TphpConfig::$obStart[$key] = [];
            }
            foreach ($config as $k => $v) {
                TphpConfig::$obStart[$key][$k] = $v;
            }
        } else {
            TphpConfig::$obStart[$key] = $config;
        }
    }

    /**
     * 获取ob_start信息
     * @param string $key
     * @return mixed|null
     */
    public static function getObStartValue($key = '')
    {
        $osc = TphpConfig::$obStart;
        if (empty($key) || !is_string($key)) {
            return $osc;
        }

        if (!is_array($osc)) {
            return null;
        }
        return $osc[$key];
    }

    /**
     * 退出时保存Session
     */
    public static function exit($message = null)
    {
        if (is_object($message)) {
            if (method_exists($message, 'render')) {
                $message = $message->render();
            }
        }
        $request = $GLOBALS['request'];
        if (!empty($request)) {
            $session = $request->getSession();
            if (!empty($session)) {
                $session->save();
            }
        }
        // 退出时加载静态文件
        Handle::loadStatic();
        if (isset($message)) {
            exit($message);
        }
        exit();
    }

    /**
     * 获取展示数据
     * @param array $args
     * @param bool $isArray
     * @return array|string
     */
    public static function getExitJson($args = [], $isArray = false)
    {
        $argsNum = count($args);
        if ($argsNum <= 0) {
            $args = [1, "操作成功"];
        } elseif ($argsNum == 1) {
            $args0 = $args[0];
            if (is_string($args0) || $isArray) {
                return $args0;
            } elseif (is_array($args0) || is_object($args0)) {
                return json_encode($args0, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($args0)) {
                if ($args0) {
                    return 'true';
                } else {
                    return 'false';
                }
            } elseif (!is_integer($args0)) {
                return $args0;
            }
            $args[1] = "操作成功";
        }
        $obj = [];
        foreach ($args as $key => $val) {
            if ($key == 0) {
                $obj['code'] = $val;
            } elseif ($key == 1) {
                if (is_array($val)) {
                    $obj['msg'] = json_encode($val, JSON_UNESCAPED_UNICODE);
                } else {
                    if (empty($val)) $val = "";
                    $obj['msg'] = $val;
                }
            } elseif ($key == 2) {
                $obj['data'] = $val;
            } elseif ($key == 3) {
                $obj['url'] = $val;
            }
        }

        if ($isArray) {
            return $obj;
        }

        $json = json_encode($obj, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode([
                'code' => 0,
                'msg' => '数据解析失败'
            ], JSON_UNESCAPED_UNICODE);
        }
        return $json;
    }

    /**
     * 空对象
     */
    public static function exitJson($args = [])
    {
        if (!Register::$isDump) {
            try {
                self::header('Content-Type:application/json; charset=utf-8');
            } catch (Exception $e) {
                // TODO
            }
        }
        self::exit(self::getExitJson($args));
    }

    /**
     * 获取 import 文件路径
     * @param $path
     * @return String
     */
    private static function __importGetPhpFile($path): String
    {
        if (file_exists($path . ".php")) {
            $filePath = $path . ".php";
        } elseif (file_exists($path . ".class.php")) {
            $filePath = $path . ".class.php";
        } else {
            $filePath = '';
        }
        return $filePath;
    }

    /**
     * 获取import中的文件及参数传递
     * @return null
     */
    public static function import($importName = '', &$args = [])
    {
        if (empty($importName) || !is_string($importName)) return null;
        $importName = str_replace('/', '.', $importName);
        $importName = str_replace('\\', '.', $importName);
        $classes = explode(".", $importName);
        $className = $classes[count($classes) - 1];
        $classPath = implode("/", $classes);

        if (!class_exists($className)) {
            $paths = [];
            $basePath = Register::getHtmlPath(true);
            $domainPath = TphpConfig::$domainPath;
            $domainPathPlu = null;
            if (!is_null($domainPath) && $basePath) {
                if (!empty($domainPath->plu)) {
                    $domainPathPlu = $domainPath->plu;
                }

                $pluDir = $domainPath->basePluPath;
                if (!empty($pluDir)) {
                    // 1.自定义插件路径
                    $paths[] = $basePath . "/plugins/{$pluDir}/import";

                    // 2.系统插件路径
                    foreach (Register::$viewPaths as $vp) {
                        $paths[] = "{$vp}/html/plugins/{$pluDir}/import";
                    }
                }
            }

            if (!empty($domainPathPlu)) {
                // 3.模块引用到的插件路径
                $dppPaths = $domainPathPlu->getBasePaths('import');
                foreach ($dppPaths as $dppPath) {
                    $paths[] = $dppPath;
                }
            }

            // 4.总模块路径 /html/import
            $basePath && $paths[] = $basePath . "/import";

            // 5.TPHP系统路径
            foreach (Register::$viewPaths as $vp) {
                $paths[] = "{$vp}/import";
            }

            $paths = array_unique($paths);

            $filePath = "";
            foreach ($paths as $path) {
                if (!is_dir($path)) {
                    continue;
                }

                $filePath = self::__importGetPhpFile("{$path}/{$classPath}");
                if (!empty($filePath)) {
                    break;
                }
            }

            if (!empty($filePath)) {
                include_once $filePath;
            }
        }
        if (!class_exists($className)) {
            return null;
        }
        if (empty($args)) return new $className();
        $argStr = "";
        foreach ($args as $key => $val) {
            if (empty($argStr)) {
                $argStr = "\$args[{$key}]";
            } else {
                $argStr .= ", \$args[{$key}]";
            }
        }
        $fun = [];
        eval("\$fun = new {$className}({$argStr});");
        return $fun;
    }

    /**
     * 获取浏览器名称
     * @return array
     */
    public static function getBrowser()
    {
        if (!empty(self::$browser)) {
            return [self::$browser, self::$browserVersion];
        }
        
        $sys = $_SERVER['HTTP_USER_AGENT'];  //获取用户代理字符串

        if (stripos($sys, "Firefox/") > 0) {
            // 火狐

            preg_match("/Firefox\/([^;)]+)+/i", $sys, $b);
            $browser = "Firefox";
            $browserVersion = $b[1];
        } elseif (stripos($sys, "Maxthon") > 0) {
            // 傲游

            preg_match("/Maxthon\/([\d\.]+)/", $sys, $aoyou);
            $browser = "Aoyou";
            $browserVersion = $aoyou[1];
        } elseif (stripos($sys, "MSIE") > 0) {
            // IE

            preg_match("/MSIE\s+([^;)]+)+/i", $sys, $ie);
            $browser = "IE";
            $browserVersion = $ie[1];
        } elseif (stripos($sys, "OPR") > 0) {
            // Opera

            preg_match("/OPR\/([\d\.]+)/", $sys, $opera);
            $browser = "Opera";
            $browserVersion = $opera[1];
        } elseif (stripos($sys, "Edge") > 0) {
            // win10 Edge浏览器 添加了chrome内核标记 在判断Chrome之前匹配

            preg_match("/Edge\/([\d\.]+)/", $sys, $Edge);
            $browser = "Edge";
            $browserVersion = $Edge[1];
        } elseif (stripos($sys, "SE 2.X") > 0) {
            // 搜狗 默认IE内核

            $browser = "IE";
            $browserVersion = 'Sogou';
        } elseif (stripos($sys, "Chrome") > 0) {
            // 谷歌

            preg_match("/Chrome\/([\d\.]+)/", $sys, $google);
            $browser = "Chrome";
            $browserVersion = $google[1];
        } elseif (stripos($sys, 'rv:') > 0 && stripos($sys, 'Gecko') > 0) {
            // IE

            preg_match("/rv:([\d\.]+)/", $sys, $IE);
            $browser = "IE";
            $browserVersion = $IE[1];
        } else {
            $browser = "Other";
            $browserVersion = "0.0";
        }

        self::$browser = $browser;
        self::$browserVersion = $browserVersion;
        
        return [$browser, $browserVersion];
    }

    /**
     * 获取中文转换长度
     * @param $str
     * @param int $length
     * @param bool $isChange
     * @return null|string|string[]
     */
    public static function mbSubstrChange($str, $length = 200, $isChange = false)
    {
        if ($isChange) {
            $str = mb_substr($str, 0, $length);
            $stack = [];
            $strLen = strlen($str);
            $newStr = "";
            for ($i = 0; $i < $strLen; $i++) {
                $si = $str[$i];
                if ($si == '<') {
                    $i++;
                    if ($i < $strLen) {
                        if ($str[$i] == "/") {
                            array_pop($stack);
                            for (; $i < $strLen; $i++) {
                                if ($str[$i] == '>') {
                                    break;
                                }
                            }
                        } else {
                            $iName = "";
                            $isOk = true;
                            $isRemOne = false;
                            $isRemTwo = false;
                            for (; $i < $strLen; $i++) {
                                if ($str[$i] == '"') {
                                    $isRemTwo = !$isRemTwo;
                                } elseif ($str[$i] == "'") {
                                    $isRemOne = !$isRemOne;
                                }
                                if ($str[$i] == '>') {
                                    break;
                                } elseif ($str[$i] == ' ') {
                                    $isOk = false;
                                } elseif ($isOk) {
                                    $iName .= $str[$i];
                                }
                            }
                            $isPush = true;
                            if ($str[$i] == '>') {
                                if ($str[$i - 1] == '/') {
                                    $isPush = false;
                                }
                            } else {
                                if ($i >= $strLen) {
                                    $isRemOne && $str .= "'";
                                    $isRemTwo && $str .= '"';
                                    $str .= '>';
                                }
                            }
                            $isPush && array_push($stack, $iName);
                        }
                    }
                } else {
                    $newStr .= $si;
                    $i++;
                }
            }
            foreach ($stack as $s) {
                $str .= "</{$s}>";
            }
        } else {
            $str = preg_replace("/(<(?:\/*)[^>]*>)/i", "", $str);
            $str = mb_substr($str, 0, $length);
            $str = str_replace("<", "&lt;", $str);
            $str = str_replace(">", "&gt;", $str);
        }
        return $str;
    }
    
    /**
     * 设置页面内css代码，用作于样式微调
     * @param string $codeStr 代码
     * @param string $prevMessage 注释
     * @param string $type 类型： style、 script
     */
    public static function setStyleOrScript($codeStr = '', $prevMessage = '', $type = 'style', $isTop = false)
    {
        if (!in_array($type, ['style', 'script'])) {
            return;
        }
        
        if (!is_string($codeStr)) {
            return;
        }

        $codeStr = rtrim($codeStr);
        if (empty($codeStr)) {
            return;
        }

        if ($type == 'style') {
            $codeStr = \Tphp\Scss\Run::getCode($codeStr);

            if (empty($codeStr)) {
                return;
            }
        }

        if (is_string($prevMessage)) {
            $prevMessage = trim($prevMessage);
            if (!empty($prevMessage)) {
                $prevMessage = str_replace("/*", "\\/*", $prevMessage);
                $prevMessage = str_replace("*/", "*\\/", $prevMessage);
                $codeStr = "/* {$prevMessage} */\n{$codeStr}";
            }
        }

        if (!is_array(TphpConfig::$obStart)) {
            TphpConfig::$obStart = [];
        }

        $osc = &TphpConfig::$obStart;

        if (!isset($osc[$type]) || !is_array($osc[$type])) {
            $osc[$type] = [];
        }

        $osc[$type][] = [rtrim($codeStr), $isTop];
    }
    
    /**
     * 错误页面处理
     * @param $code
     * @param string $message
     * @param array $headers
     */
    public static function abort($code = 404, $message = '', array $headers = [])
    {
        $tpl = TphpConfig::$domain['tpl'];
        $errorPaths = [
            "{$tpl}/layout/errors",
            "layout/errors",
        ];
        $errorTpl = "";
        foreach ($errorPaths as $ep) {
            $eTpl = "{$ep}/{$code}";
            if (view()->exists($eTpl)) {
                $errorTpl = $eTpl;
                break;
            }
        }

        if (empty($errorTpl)) {
            if (is_array($message)) {
                $message = json_encode($message, true);
            }
            abort($code, $message, $headers);
        }

        if (!is_array($message)) {
            abort($code, $message, $headers);
        }

        if (is_array($headers) && !empty($headers)) {
            foreach ($headers as $h) {
                self::header($h);
            }
        }
        http_response_code($code);
        self::exit(view($errorTpl, $message));
    }

    /**
     * 头部文件设置
     * @param string $cmd
     * @param string $cValue
     */
    public static function header($cmd = '', $cValue = '')
    {
        $headers = [];
        if (is_string($cmd)) {
            $cmd = trim($cmd);
            if (!empty($cmd)) {
                if (is_string($cValue)) {
                    $cValue = trim($cValue);
                } else {
                    $cValue = '';
                }
                $headers[$cmd] = $cValue;
            }
        } elseif (is_array($cmd)) {
            foreach ($cmd as $k => $v) {
                if (is_string($v)) {
                    $v = trim($v);
                } else {
                    $v = '';
                }
                if (is_string($k)) {
                    $k = trim($k);
                    if (empty($k)) {
                        continue;
                    }
                    $headers[$k] = $v;
                } elseif (!empty($v)) {
                    $headers[$v] = '';
                }
            }
        }

        $realHeaders = [];
        foreach ($headers as $key => $val) {
            if (empty($val)) {
                $pos = strpos($key, ':');
                if ($pos > 0) {
                    $k = trim(substr($key, 0, $pos));
                    $v = trim(substr($key, $pos + 1));
                    if (!empty($k) && !empty($v)) {
                        $realHeaders[$k] = $v;
                    }
                }
            } else {
                $realHeaders[$key] = $val;
            }
        }
        
        if (empty($realHeaders)) {
            return;
        }

        $hds = get_ob_start_value('header');
        empty($hds) && $hds = [];
        
        foreach ($realHeaders as $key => $val) {
            $hds[$key] = $val;
        }
        
        set_ob_start_value($hds, 'header');
    }

    /**
     * 运行头部文件
     */
    public static function runHeader()
    {
        $hds = get_ob_start_value('header');
        if (empty($hds)) {
            return;
        }
        
        foreach ($hds as $key => $val) {
            header("{$key}: {$val}");
        }
    }
}
