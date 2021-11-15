<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic;

use Tphp\Register;

class Apcu
{
    private static $static;

    public function __construct()
    {
        $cacheId = '__apcu_set_time_cache__';
        $time = \Cache::get($cacheId);
        if (empty($time)) {
            $time = time();
            \Cache::forever($cacheId, $time);
        }
        $apcuTime = apcu_fetch($cacheId);
        if ($apcuTime !== $time) {
            $this->getApcu();
            // 更新 Apcu 缓存
            apcu_store($cacheId, $time);
        }
    }

    /**
     * 初始化
     * @return Apcu
     */
    public static function __init() : Apcu
    {
        if (empty(self::$static)) {
            self::$static = new static();
        }
        return self::$static;
    }

    /**
     * Apcu参数说明
     * @param array $configs 配置参数
     * @param $data 数据
     * @return null|string
     */
    public function apcu($configs = [], $data = null)
    {
        $tplCache = getenv('TPL_CACHE');
        if (!empty($tplCache)) {
            $tplCache = strtolower(trim($tplCache));
        }
        if ($tplCache === 'false') {
            $this->getApcu();
        }
        if (empty($configs)) return $data;
        if (is_string($configs)) { //当配置直接为字符串时处理一次任务
            return $this->apcuReturn($configs, $data);
        } else {
            foreach ($configs as $key => $val) {
                if (is_string($key)) {
                    $config = [$key, $val];
                } elseif (is_string($val)) {
                    $config = $val;
                } else {
                    $k = $val[0];
                    $v = [];
                    $ki = 0;
                    foreach ($val as $kk => $vv) {
                        if ($ki > 0) {
                            if (is_string($kk)) {
                                $v[] = [$kk => $vv];
                            } else {
                                $v[] = $vv;
                            }
                        }
                        $ki++;
                    }
                    $config = [$k, $v];
                }
                $data = $this->apcuReturn($config, $data);
            }
            return $data;
        }
    }

    /**
     * 获取Apcu返回参数
     * @param array $config
     * @param null $data
     * @return null|string
     */
    public function apcuReturn($config = [], $data = null)
    {
        if (is_string($config)) { //配置参数为字符类型分析
            $keyName = trim($config);
        } else {
            $keyName = trim($config[0]);
            isset($config[1]) && $argVal = $config[1];
        }

        $fChar = $keyName[0];
        //加减乘除特殊处理、#为系统函数
        if (in_array($fChar, ['+', '-', '*', '/', '#'])) {
            $tmp = trim(substr($keyName, 1, strlen($keyName) - 1));
            $keyName = $fChar;
            if (!empty($tmp)) {
                if (empty($argVal)) {
                    $argVal = $tmp;
                } else {
                    array_unshift($argVal, $tmp);
                }
            }
        }

        if (empty($keyName)) return $data;

        $keyName = strtolower($keyName); //使配置项不区分大小写
        $funcStr = $this->apcuFetch($keyName);
        if (empty($funcStr)) return $data;

        //读取调用函数方法名称
        $funcName = $this->apcuFetch('_sysnote_')[$keyName]['func'];
        if (empty($funcName)) return $data;
        if (!function_exists($funcName)) {
            eval("function {$funcName}{$funcStr}");
        }

        $argStr = "";
        if (is_array($argVal)) {
            foreach ($argVal as $key => $val) {
                $argStr .= ", \$argVal[{$key}]";
            }
        } elseif (isset($argVal)) {
            $argStr .= ", \$argVal";
        }
        $retData = "";
        eval("\$retData = {$funcName}(\$data{$argStr});");
        return $retData;
    }

    /**
     *str:字符串
     *start:开始字符串
     *end:结束字符串
     */
    private function getSubStr($str, $start = "", $end = "")
    {
        if (!empty($start)) {
            $pos = strpos($str, $start);
            if ($pos === 0 || $pos > 0) {
                $pos = $pos + strlen($start);
                $str = substr($str, $pos);
            } else {
                return "";
            }
        }
        if (!empty($end)) {
            $pos = strpos($str, $end);
            if ($pos > 0) {
                $str = substr($str, 0, $pos);
            } else {
                return "";
            }
        }
        return $str;
    }

    /**
     * 输出匿名函数代码
     * @param $closure
     */
    private function getClosure($closure)
    {
        try {
            $func = new \ReflectionFunction($closure);
        } catch (\ReflectionException $e) {
            echo $e->getMessage();
            return;
        }

        $start = $func->getStartLine() - 1;
        $end = $func->getEndLine() - 1;
        $fileName = $func->getFileName();

        $code = implode("", array_slice(file($fileName), $start, $end - $start + 1));
        $code = $this->getSubStr($code, "function");
        return $code;
    }

    /**
     * 当程序运行时不存在方法时自动更新
     * @return mixed
     */
    public function getApcu()
    {
        apcu_clear_cache();
        // TPHP系统函数
        $ret = [];
        $retFunc = [];
        $msgs = [];
        foreach (Register::$viewPaths as $vp) {
            $vp .= "/function";
            if (is_dir($vp)) {
                $this->setFunc($vp, $vp, $ret, $retFunc, $msgs);
            }
        }

        // 用户自定义函数
        $funcPath = Register::getHtmlPath(true) . "/function";
        if (is_dir($funcPath)) {
            $this->setFunc($funcPath, $funcPath, $ret, $retFunc, $msgs);
        }

        $apcuCacheId = $this->getApcuCacheId();
        apcu_store($apcuCacheId . '_sysmenu_', $ret);
        apcu_store($apcuCacheId . '_sysnote_', $retFunc);
        return $msgs;
    }

    /**
     * 获取路径唯一ID，避免多项目冲突
     * @return bool|string
     */
    private function getApcuCacheId()
    {
        if (!empty($this->apcuCacheId)) {
            return $this->apcuCacheId;
        }

        $this->apcuCacheId = substr(md5(base_path()), 8, 8) . "#";

        return $this->apcuCacheId;

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
     * 获取数据
     * @param string $key
     * @return mixed|null
     */
    public function apcuFetch($key = '')
    {
        return apcu_fetch($this->getApcuCacheId() . $key);
    }

    /**
     * 设置内置函数
     * @param $funcPath
     * @param $path
     * @param array $ret
     * @param array $retFunc
     * @param array $msgs
     */
    private function setFunc($funcPath, $path, &$ret = [], &$retFunc = [], &$msgs = [])
    {
        $tFiles = [];
        $dirs = [];
        foreach (scandir($path) as $val) {
            if ($val == '.' || $val == '..') continue;
            if (is_dir($path . '/' . $val)) {
                $dirs[] = $val;
            } else {
                $tFiles[] = $val;
            }
        }

        if (!empty($tFiles)) { //文件类型
            $keyFlag = "";
            $func = null;
            $keyName = "";
            $note = "";
            $args = "";
            $apcuCacheId = $this->getApcuCacheId();
            foreach ($tFiles as $val) {
                $pt = $path . '/' . $val;
                if ($val == 'name') {
                    $keyName = import('XFile')->read($pt);
                } elseif ($val == 'ini.php') {
                    $tArr = $this->includeFile($pt);
                    $keyName = $tArr['name'];
                    $keyFlag = $tArr['flag'];
                    $note = $tArr['note'];
                    $args = $tArr['args'];
                } elseif ($val == 'func.php') {
                    $func = $this->includeFile($pt);
                }
            }
            $keyFlag = strtolower($keyFlag);
            if (!empty($keyFlag) && !empty($func)) {
                apcu_store($apcuCacheId . $keyFlag, $this->getClosure($func));
            }

            if (!empty($keyName)) {
                $indPath = str_replace($funcPath, "", $path);
                $indPath = trim($indPath, '/');
                $indArr = explode("/", $indPath);
                $indCot = count($indArr);
                if ($indCot > 0) {
                    $ind = "";
                    for ($i = 0; $i < $indCot - 1; $i++) {
                        $ind .= "['{$indArr[$i]}']['_next_']";
                    }

                    $ind .= "['{$indArr[$indCot - 1]}']";
                    eval("\$ret{$ind}['_name_'] = \$keyName;");
                    if (!empty($args)) eval("\$ret{$ind}['_args_'] = \$args;");
                    if (!empty($keyFlag)) {
                        eval("\$ret{$ind}['_flag_'] = \$keyFlag;");
                        if (empty($retFunc[$keyFlag])) {
                            $retFunc[$keyFlag] = [
                                'path' => $indPath,
                                'func' => 'f_' . substr(md5($indPath), 8, 16)
                            ];
                            if (!empty($note)) $retFunc[$keyFlag]['note'] = $note;
                        } else {
                            $msgs[] = $retFunc[$keyFlag]['path'] . "和{$indPath}的标志'{$keyFlag}'重复！";
                        }
                    }
                }
            }
        }

        foreach ($dirs as $val) { //文件夹类型
            $this->setFunc($funcPath, $path . '/' . $val, $ret, $retFunc, $msgs);
        }
    }
}
