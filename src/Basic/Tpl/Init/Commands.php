<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl\Init;

use Tphp\Basic\Tpl\Data as TplData;
use Tphp\Register;
use Tphp\Config as TphpConfig;

/**
 * 主类
 * Trait Commands
 * @package Tphp\Basic\Tpl\Init
 */
trait Commands
{
    /**
     * 设置头部TDK
     * @param $config
     * @param bool $useBool
     */
    public static function setSeo($config, $useBool = true)
    {
        if ($useBool) {
            $configInfo = get_ob_start_value('seo');
            empty($configInfo) && $configInfo = [];
            if (empty($config['title'])) $config['title'] = $configInfo['title'];
            if (empty($config['keywords'])) $config['keywords'] = $configInfo['keywords'];
            if (empty($config['description'])) $config['description'] = $configInfo['description'];
        }
        set_ob_start_value($config, 'seo');
    }

    /**
     * 设置SEO
     * @param $str
     * @param string $type
     */
    private static function setTdk($str, $type = '')
    {
        $seo = get_ob_start_value('seo');
        empty($seo) && $seo = [];
        if (is_null($str)) {
            return $seo[$type];
        }
        $seo[$type] = $str;
        set_ob_start_value($seo, 'seo');
    }

    /**
     * 设置标题
     * @param null $str
     * @return mixed
     */
    public static function title($str = null)
    {
        return self::setTdk($str, 'title');
    }

    /**
     * 设置关键词
     * @param null $str
     * @return mixed
     */
    public static function keywords($str = null)
    {
        return self::setTdk($str, 'keywords');
    }

    /**
     * 设置描述
     * @param null $str
     * @return mixed
     */
    public static function description($str = null)
    {
        return self::setTdk($str, 'description');
    }
    
    /**
     * 设置头部TDK
     * @param $config
     * @param bool $useBool
     */
    public function seo($config, $useBool = true)
    {
        return self::setSeo($config, $useBool);
    }

    /**
     * Layout 设置
     * @param int $layout
     * @return mixed
     */
    public function layout($layout = -100)
    {
        if ($layout == -100) {
            return $this->layout;
        }

        if (is_bool($layout) || is_null($layout)) {
            $this->layout = $layout;
        } elseif (is_string($layout)) {
            $this->layout = trim($layout);
        }
    }

    /**
     * 注册变量
     * @param string $keyName
     * @param null $data
     * @param bool $isOverflow
     */
    public function register($keyName = '', $data = null, $isOverflow = false)
    {
        if (!is_string($keyName)) {
            return;
        }

        $keyName = trim($keyName);

        // 方法验证，必须由字母数字或下划线组成，并且首字母不能为数字
        if (empty($keyName) || !preg_match('/^[^0-9]\w+$/', $keyName)) {
            return;
        }

        if (!$isOverflow) {
            if (empty($this->$keyName)) {
                $isOverflow = true;
            }
        }

        if ($isOverflow) {
            $this->$keyName = $data;
        }
    }


    /**
     * 获取顶部路径
     * @param bool $isTop
     * @return string
     */
    public function getRealTplTop($isTop = false)
    {
        $btp = "";
        if ($isTop) {
            $btp = Register::getTopPath("/");
        } else {
            if (!is_null(Register::$tplPath)) {
                $btp = trim(trim(Register::$tplPath, "/"));
            }
        }
        if (empty($btp)) {
            // 如果根目录未找到则使用domians配置tpl目录
            $dc = TphpConfig::$domain;
            if (is_array($dc) && !empty($dc['tpl'])) {
                $dct = str_replace("\\", "/", trim($dc['tpl']));
                $btp = trim($dct, "/");
            }
        }
        return $btp;
    }

    /**
     * 获取模板路径
     * @param string $tpl
     * @return string
     */
    public function getTplPath($tpl = '')
    {
        if (empty($tpl) || !is_string($tpl)) {
            return '';
        }

        $tpl = str_replace("\\", "/", $tpl);
        $pos = strrpos($tpl, ".");
        if ($pos > 0) {
            $tplType = trim(substr($tpl, $pos + 1));
            if (strrpos($tplType, "/") === false) {
                $this->tplType = $tplType;
                $tpl = substr($tpl, 0, $pos);
            }
        } elseif ($pos !== false && !in_array($tpl[1], ['.', '/'])) {
            $this->tplType = ltrim($tpl, " .");
            $tpl = '';
        }

        if ($tpl[0] == '/' && $tpl[1] == '/') {
            $tpl = "/" . TphpConfig::$domain['tpl'] . "/" . ltrim($tpl, "/");
        }

        return $tpl;
    }

    /**
     * 获取TPL相对路径对应的绝对路径
     * @param $tpl
     * @param string $next
     * @param bool $isTop
     * @return mixed|string
     */
    public function getRealTpl($tpl, $next = "", $isTop = false)
    {
        if ($this->isRoot) {
            $btp = $this->getRealTplTop($isTop);
            if (!empty($btp)) {
                if (empty($next)) {
                    $next = $btp;
                } else {
                    $next = rtrim($btp, " \\/") . "/" . ltrim($next, " \\/");
                }
            }
        }

        if (empty($tpl)) {
            if (empty($next)) {
                return "";
            } else {
                return $next;
            }
        }
        if (!is_string($tpl) || !is_string($next)) return "";

        $flag = "~";
        $flagLen = strlen($flag);

        $tpl = str_replace("\\", "/", $tpl);
        $tpl = str_replace("../", $flag, $tpl);
        $tpl = str_replace("./", "", $tpl);
        $tpl = trim($tpl);

        //当路径的首字母为'/'时直接返回对应模板路径
        if ($tpl[0] == '/') {
            $tpl = trim(trim($tpl, "/"));
            if (strpos($tpl, $flag) === false) {
                return $tpl;
            }
            return "";
        }
        $tpl = trim(trim($tpl, "/"));
        $next = trim(trim($next, "/"));
        if (empty($next)) return $tpl;

        if (strpos($tpl, $flag) === false) {
            return $next . "/" . $tpl;
        }

        //获取相对路径对应的绝对路径
        $nextArr = explode("/", $next);
        while (substr($tpl, 0, $flagLen) == $flag) {
            $cNext = count($nextArr);
            if ($cNext <= 0) break;
            $tpl = substr($tpl, $flagLen);
            unset($nextArr[$cNext - 1]);
        }

        $pos = strrpos($tpl, $flag);
        if ($pos !== false) {
            return "";
        }
        $baseDir = trim(trim(implode("/", $nextArr), "/"));
        return $baseDir . "/" . $tpl;
    }

    /**
     * 获取当前cacheId替换字符串
     * @param $str
     * @return mixed
     */
    private function getCacheIdStr($str)
    {
        $cacheId = str_replace("\\", "_", $str);
        $cacheId = str_replace(".", "_", $cacheId);
        $cacheId = str_replace("/", "_", $cacheId);
        return $cacheId;
    }

    /**
     * 获取制定的cacheId对于的路径
     * @param $tpl
     * @param string $ext
     * @return mixed|string
     */
    public function getCacheId($tpl = "", $ext = "")
    {
        $path = $this->getRealTpl($tpl, self::$topTpl, true);
        $path = trim($path, "/");
        $cacheId = $this->getCacheIdStr($path);
        !empty($ext) && $cacheId .= "_" . $ext;
        return $cacheId;
    }

    /**
     * 设置插件配置
     * @param $data 原数据
     * @param null $obj Tpl对象
     */
    public static function setPluginsConfig(&$data, $obj = null)
    {
        $plu = $data['plu'];
        if (empty($plu) || !is_array($plu)) {
            return;
        }

        $config = $plu['caller'];
        if (!is_string($config)) {
            return;
        }
        $config = trim($config);
        if (empty($config)) {
            return;
        }
        if (empty($obj)) {
            $obj = new static();
        }
        $class = plu($obj->getPluDir($data))->setTpl($obj)->caller($config);

        if (!is_object($class)) {
            return;
        }

        if (method_exists($class, 'data')) {
            $pData = call_user_func_array([$class, 'data'], []);
            if (is_array($pData) && is_array($pData['plu']) && is_set($pData['plu']['caller'])) {
                unset($pData['plu']['caller']);
            }
        } else {
            $pData = null;
        }

        // 优先使用plu中的conn配置，再次是config中的conn配置
        $conn = $data['plu']['conn'];
        if (empty($conn)) {
            if (is_array($data['config']) && !empty($data['config']['conn'])) {
                $conn = $data['config']['conn'];
            }
        }
        if (!empty($pData)) {
            $obj->arrayMerge($data, $pData);
        }
        if (!empty($conn) && is_array($data['config'])) {
            $data['config']['conn'] = $conn;
        }
        $data['plu']['caller'] = $class;
    }

    /**
     * 数组key转化为小写（过滤空值）
     * @param $data
     * @return array
     */
    public function keyToLower($data)
    {
        if (!is_array($data)) {
            if (is_object($data)) {
                $data = json_decode(json_encode($data, true), true);
            }
            if (!is_array($data)) {
                return $data;
            }
        }
        $newData = [];
        if (empty($data)) return $newData;
        foreach ($data as $key => $val) {
            (isset($val) || is_bool($val) || is_numeric($val)) && $newData[strtolower(trim($key))] = $val;
        }
        return $newData;
    }

    /**
     * 数组key转化为小写（转化所有）
     * @param $data
     */
    public function keyToLowers(&$data)
    {
        if (is_array($data)) {
            $newData = $data;
            $data = [];
            foreach ($newData as $key => $val) {
                if (is_int($key)) {
                    $key = (int)$key;
                }
                is_string($key) && $key = strtolower(trim($key));
                $data[$key] = $val;
                if (is_array($val)) {
                    $this->keyToLowers($data[$key]);
                }
            }
        }
    }

    /**
     * 数组key转化为小写（不过滤空值）
     * @param $data
     * @return array
     */
    public function keyToLowerOrNull($data)
    {
        if (!is_array($data)) {
            if (is_object($data)) {
                $data = json_decode(json_encode($data, true), true);
            }
            if (!is_array($data)) {
                return $data;
            }
        }
        $newData = [];
        if (empty($data)) return $newData;
        $fn = $this->fieldIsNum;
        foreach ($data as $key => $val) {
            if (is_null($val)) {
                if ($fn[$key]) {
                    $val = 0;
                } else {
                    $val = null;
                }
            }
            $newData[strtolower(trim($key))] = $val;
        }
        return $newData;
    }

    /**
     * 字段合并处理
     * @param $config
     * @param $newConfig
     * @param $arrConfig
     * @param $kvsConfig
     */
    private function arrayMergeField($config, &$newConfig, &$arrConfig, &$kvsConfig)
    {
        foreach ($config as $c) {
            if (is_array($c)) {
                foreach ($c as $ck => $cv) {
                    if (is_array($cv) && is_string($cv[3])) {
                        $c[$ck][3] = [$cv[3]];
                    }
                }
            }
            $k = md5(json_encode($c, true));
            if (!isset($kvsConfig[$k])) {
                $kvsConfig[$k] = true;
                if (is_array($c)) {
                    $arrConfig[] = $c;
                } else {
                    $newConfig[] = $c;
                }
            }
        }
    }

    /**
     * 合并配置数据循环下标
     * @param null $data
     * @param array $retKey
     * @param array $retVal
     * @return array|void
     */
    private function arrayMergeLoop($data = null, $retKey = [], &$retVal = [])
    {
        if (empty($data)) return;
        foreach ($data as $key => $val) {
            $newKey = $retKey;
            $newKey[] = $key;
            if (!is_array($val)) {
                if (empty($val) && $val !== 0 && $val !== '0' && $val !== '' && !is_bool($val)) {
                    $retVal[] = [$newKey];
                } else {
                    $retVal[] = [$newKey, $val];
                }
            } else {
                $this->arrayMergeLoop($val, $newKey, $retVal);
            }
        }
        return $retVal;
    }

    /**
     * 合并配置数据设置
     * @param $arrData
     * @param null $key
     * @param null $value
     */
    private function arrayMergeSet(&$arrData, $key = null, $value = null)
    {
        if (is_array($key)) { //如果$key是数组
            $keyStr = "";
            $keyArr = [];
            foreach ($key as $v) {
                $v = str_replace('\\', '\\\\', $v);
                $v = str_replace('\'', '\\\'', $v);
                $keyStr .= "['{$v}']";
                $keyArr[] = $keyStr;
                eval("if(!is_array(\$arrData{$keyStr})) { unset(\$arrData{$keyStr});}");
            }

            if (empty($value) && $value !== 0 && $value !== '0' && $value !== '' && !is_bool($value)) {
                eval("unset(\$arrData{$keyStr});");
                foreach ($keyArr as $v) {
                    $vBool = false;
                    eval("if(empty(\$arrData{$v})) { unset(\$arrData{$v}); \$vBool = true;}");
                    if ($vBool) break;
                }
            } else {
                eval("\$arrData{$keyStr} = \$value;");
            }
        } else { //如果$key是字符串
            $key = str_replace('\\', '\\\\', $key);
            $key = str_replace('\'', '\\\'', $key);
            if (empty($value) && $value !== 0 && $value !== '0' && $value !== '' && !is_bool($value)) {
                unset($arrData[$key]);
            } else {
                eval("if(!is_array(\$arrData['{$key}'])) { unset(\$arrData['{$key}']);}");
                $arrData[$key] = $value;
            }
        }
    }

    /**
     * 合并数组数据
     * @param $data
     * @param $addData
     */
    public function arrayMerge(&$data, $addData, $isField = true)
    {
        if (isset($addData['view'])) {
            // 删除view防止大数据传输
            unset($addData['view']);
        }
        if ($isField && isset($addData['config']) && !empty($addData['config']['field'])) {
            // 删除config.field配置避免重复合并
            if (isset($data['config']) && !empty($data['config']['field'])) {
                $kvsConfig = [];
                $newConfig = [];
                $arrConfig = [];
                $this->arrayMergeField($data['config']['field'], $newConfig, $arrConfig, $kvsConfig);
                $this->arrayMergeField($addData['config']['field'], $newConfig, $arrConfig, $kvsConfig);
                foreach ($arrConfig as $c) {
                    $newConfig[] = $c;
                }
                $data['config']['field'] = $newConfig;
            } else {
                !isset($data['config']) && $data['config'] = [];
                $data['config']['field'] = $addData['config']['field'];
            }
            unset($addData['config']['field']);
        }
        $newData = $this->arrayMergeLoop($addData);
        if (empty($newData)) return;
        foreach ($newData as $val) {
            if (count($val[0]) == 1) {
                $this->arrayMergeSet($data, $val[0][0], $val[1]);
            } else {
                $this->arrayMergeSet($data, $val[0], $val[1]);
            }
        }
    }

    /**
     * 获取GET和POST属性替换
     * @param $str
     * @param $sLeft
     * @param $sRight
     * @param string $method
     * @return string
     */
    private function getDataForArgsPos($str, $sLeft, $sRight, $method = "get")
    {
        $sRightLen = strlen($sLeft);
        $sRightLen = strlen($sRight);
        $pos = strpos($str, $sLeft);
        if (is_numeric($pos) && $pos >= 0) {
            $strR = substr($str, $pos + $sRightLen);
            $posR = strpos($strR, $sRight);
            if ($posR > 0) {
                $tStr = substr($strR, $posR + $sRightLen);
                !empty($tStr) && $tStr = $this->getDataForArgsPos($tStr, $sLeft, $sRight, $method);
                $v = trim(substr($strR, 0, $posR));
                if ($method == 'get') {
                    $vVal = $_GET[$v];
                    $this->reqArgs['get'][] = $v;
                } elseif ($method == 'post') {
                    $vVal = $_POST[$v];
                    $this->reqArgs['post'][] = $v;
                } else {
                    $vArr = explode(".", $v);
                    $dv = \Illuminate\Support\Facades\Session::get($vArr[0]);
                    if (empty($dv)) {
                        $dv = "";
                    } else {
                        unset($vArr[0]);
                        foreach ($vArr as $val) {
                            if (is_string($val) || is_int($val)) {
                                $dv = $dv[$val];
                                if (empty($dv)) {
                                    $dv = "";
                                    break;
                                }
                            } else {
                                break;
                            }
                        }
                    }
                    $vVal = $dv;
                    $this->reqArgs['session'][] = $v;
                }
                $strTmp = substr($str, 0, $pos) . $vVal . $tStr;
            } else {
                $strTmp = $str;
            }
        } else {
            $strTmp = $str;
        }
        return $strTmp;
    }

    /**
     * 数据获取提交处理
     * @param $data
     * @return mixed
     */
    public function getDataForArgs(&$data)
    {
        foreach ($data as $key => $val) {
            if (is_string($val)) {
                $strTmp = $this->getDataForArgsPos($val, "_[", "]_");
                $data[$key] = $this->getDataForArgsPos($strTmp, "_#", "#_", "post");
                $data[$key] = $this->getDataForArgsPos($strTmp, "_%", "%_", "session");
            } elseif (is_array($val)) {
                $data[$key] = $this->getDataForArgs($data[$key]);
            }
        }
        return $data;
    }

    /**
     * 设置浏览器默认参数配置
     * @param $data
     */
    private function setDataForInitUrl(&$config)
    {
        if (isset($config['config'])) {
            $sort = $_GET['_sort'];
            if (!empty($sort)) {
                $order = $_GET['_order'];
                if (!empty($order) && in_array($order, ['asc', 'desc'])) {
                    $co = $config['config']['order'];
                    $coNew = [$sort => $order];
                    if (!empty($co) && is_array($co)) {
                        foreach ($co as $key => $val) {
                            if ($key != $sort) {
                                $coNew[$key] = $val;
                            }
                        }
                    }
                    $config['config']['order'] = $coNew;
                }
            }
        }
    }

    /**
     * 获取data.php文件信息
     * @param bool $isSet 是否设置
     * @return array|mixed
     */
    private function getDataFile($isSet = false)
    {
        //先是数据配置读取
        $tplDataPath = $this->dataPath . 'data.php';
        $isDataIni = $this->dataIni['reset'];
        if (file_exists($tplDataPath) || $isSet || $isDataIni) {
            $tConf = $this->config;

            if (empty($this->setConfig)) {
                if (isset(TphpConfig::$dataFileInfo[$tplDataPath])) {
                    $dataTmp = TphpConfig::$dataFileInfo[$tplDataPath];
                } else {
                    TphpConfig::$dataFileInfo[$tplDataPath] = [];
                    if ($isDataIni) {
                        $dataTmp = $this->dataIni['data'];
                    } else {
                        $dataTmp = $this->includeThisFile($tplDataPath);
                    }
                    self::setPluginsConfig($dataTmp, $this);
                    TphpConfig::$dataFileInfo[$tplDataPath] = $dataTmp;
                }

                // 配置重置
                $dataReset = $dataTmp['reset'];
                if (!empty($dataReset)) {
                    unset($dataTmp['reset']);
                    if (is_function($dataReset)) {
                        TphpConfig::$dataFileInfo[$tplDataPath] = $dataTmp;
                        $dataReset($dataTmp, $this);
                    }
                    TphpConfig::$dataFileInfo[$tplDataPath] = $dataTmp;
                }
            } else {
                $dataTmp = $this->setConfig;
                self::setPluginsConfig($dataTmp, $this);
            }

            // 插件支持
            $isPlugin = false;
            $table = $dataTmp['config']['table'];
            $conn = $dataTmp['config']['conn'];
            if (!(is_string($table) && is_string($conn))) {
                list($newTable, $newConn, $isPlugin, $pluFields) = $this->getSqlInit()->getPluginTable($table, $conn, $this);
                if ($isPlugin && !empty($pluFields)) {
                    if (empty($dataTmp['plu'])) {
                        $dataTmp['plu'] = [];
                    }
                    $dataTmp['plu']['field'] = $pluFields;
                }
                if ($table !== $newTable) {
                    $dataTmp['config']['table'] = $newTable;
                }
                if ($conn !== $newConn) {
                    $dataTmp['config']['conn'] = $newConn;
                }
                TphpConfig::$dataFileInfo[$tplDataPath] = $dataTmp;
            }

            !is_array($dataTmp) && $dataTmp = [];
            $data = $this->keyToLower($dataTmp);
            $add = $tConf['config']['add'];
            $edit = $tConf['config']['edit'];
            !$isSet && $this->arrayMerge($data, $tConf);
            $data = $this->getDataForArgs($data);
            !empty($add) && $data['config']['add'] = $add;
            !empty($edit) && $data['config']['edit'] = $edit;
            $this->setDataForInitUrl($data);
            if (!empty($data['css'])){
                if (is_string($data['css'])) {
                    $data['css'] = [$data['css']];
                } elseif (!is_array($data['css'])) {
                    $data['css'] = [];
                }
                foreach ($data['css'] as $dcss) {
                    if (!in_array($dcss, self::$css)) {
                        self::$css[] = $dcss;
                    }
                }
            }
            if (!empty($data['js'])){
                if (is_string($data['js'])) {
                    $data['js'] = [$data['js']];
                } elseif (!is_array($data['js'])) {
                    $data['js'] = [];
                }
                foreach ($data['js'] as $djs) {
                    if (!in_array($djs, self::$js)) {
                        self::$js[] = $djs;
                    }
                }
            }

            if ($isSet) {
                $this->config = $data;
            } elseif (!empty($data) && is_array($data)) {
                $i = 0;
                foreach ($data as $key => $val) {
                    if (!isset($tConf[$key]) && isset($val)) {
                        $tConf[$key] = $val;
                        $i++;
                    }
                }
                $i > 0 && $this->config = $tConf;
            }
        } else {
            $data = [];
        }

        return $data;
    }

    /**
     * 初始化设置数据库默认信息
     * @param bool $isCache 缓存获取
     * @param bool $isSet 是否设置
     * @param array $config 配置信息
     * @return array|mixed
     */
    public function getDataConfig($isCache = true, $isSet = false, $config = [])
    {
        if ($isSet) {
            $this->setConfig = $config;
        }

        if ($isCache && !empty($this->dataDefault) && !$isSet) {
            return $this->dataDefault;
        }
        $data = $this->getDataFile($isSet);

        $this->dataDefault = $data;

        return $this->dataDefault;
    }

    /**
     * 修改POST提交设置
     * @param $keyName
     * @param $value
     */
    public function setPostValue($keyName, $value)
    {
        $this->config['config'][$this->tplType][$keyName] = $value;
    }

    /**
     * 设置url()根路径
     * @param string $url
     */
    public function forceRootUrl($url = '')
    {
        if (!is_string($url)) {
            return;
        }

        $url = trim($url);
        if (empty($url)) {
            return;
        }
        
        if (strpos($url, "://") === false) {
            return;
        }
        
        url()->forceRootUrl($url);
    }

    /**
     * 返回为json格式数据并退出
     * @param $obj
     */
    public function exitJson()
    {
        $this->exitJson = \Tphp\Basic\Tpl\Run::getExitJson(func_get_args(), $this->isArray);
    }

    /**
     *
     * @param $url
     * @param string $url
     * @param bool $isDomain
     * @return string
     */
    public function url($url = "", $isDomain = true)
    {
        $tmp_root = $this->isRoot;
        $this->isRoot = false;
        if (empty($url)) {
            $retUrl = "/{$this->dir}";
        } else {
            $isThis = false;
            if ($url[0] === '.' && $url[1] != '.') {
                $urlDot = substr($url, 1);
                if (strpos($urlDot, '.') === false) {
                    $isThis = true;
                }
            }
            if ($isThis) {
                $retUrl = "/{$this->dir}{$url}";
            } else {
                $retUrl = $this->getRealTpl($url, "/" . $this->dir);
            }
            $this->isRoot = $tmp_root;
        }
        $argsUrl = trim($this->config['args']['url'], "/\\");
        $retUrl = trim($retUrl, "/\\");
        !empty($argsUrl) && $retUrl = $argsUrl . "/" . $retUrl;
        $retUrl = "/{$retUrl}";
        if ($isDomain) {
            return url($retUrl);
        }
        return $retUrl;
    }

    /**
     * 获取根路径
     * @param string $path
     * @return string
     */
    public function getBasePath($path = '')
    {
        $tplPath = TphpConfig::$domain['tpl'];
        $deepCount = 0;
        if (empty($path) || !is_string($path)) {
            $path = '';
        } else {
            $path = str_replace("\\", "/", $path);
            $paths = explode("../", $path);
            $pathsLast = count($paths) - 1;
            if ($paths > 1) {
                $posList = [];
                foreach ($paths as $key => $val) {
                    $val = trim($val, "/");
                    if ($val !== '') {
                        break;
                    }

                    if ($key < $pathsLast) {
                        $posList[] = $key;
                        $deepCount++;
                    }
                }
                if (count($posList) > 0) {
                    foreach ($posList as $pl) {
                        unset($paths[$pl]);
                    }
                    $path = implode("/", $paths);
                }
            }
            $path = str_replace(".", "/", $path);
            $path = trim($path, " /");
            if (!empty($path)) {
                $path = "/{$path}";
            }
        }

        if ($deepCount > 0) {
            $tplPaths = explode("/", $tplPath);
            $tplPathsLen = count($tplPaths);
            $tplPos = $tplPathsLen - $deepCount;
            if ($tplPos <= 0) {
                $tplPath = '';
            } else {
                for ($i = $tplPos; $i < $tplPathsLen; $i ++) {
                    unset($tplPaths[$i]);
                }

                $tplPath = implode("/", $tplPaths);
            }
        }

        $retPath = '';
        if (!empty($tplPath)) {
            $retPath = Register::getHtmlPath(true) . "/{$tplPath}{$path}";
            if (is_dir($retPath)) {
                return $retPath;
            }
        }

        foreach (Register::$viewPaths as $vp) {
            $vp .= "/html{$path}";
            if (is_dir($vp)) {
                $retPath = $vp;
                break;
            }
        }

        return $retPath;
    }

    /**
     * 获取视图类型路径
     * @param string $srcDir
     * @param int $deep
     * @param string $moreDir
     * @return bool|mixed|string
     */
    public function getViewDir($srcDir = '', $deep = 0, $moreDir = '')
    {
        if (!is_string($srcDir)) {
            return '';
        }
        $srcDir = str_replace("\\", ".", $srcDir);
        $srcDir = str_replace("/", ".", $srcDir);
        $srcDir = str_replace("..", ".", $srcDir);
        $srcDir = trim($srcDir, ".");
        if (is_integer($deep) && $deep > 0) {
            for ($i = 0; $i < $deep; $i ++) {
                $pos = strrpos($srcDir, '.');
                if ($pos === false) {
                    break;
                }
                $srcDir = substr($srcDir, 0, $pos);
            }
        }
        if (!empty($moreDir)) {
            $moreDir = str_replace("\\", ".", $moreDir);
            $moreDir = str_replace("/", ".", $moreDir);
            $moreDir = str_replace("..", ".", $moreDir);
            $moreDir = trim($moreDir, ".");
            if (!empty($moreDir)) {
                $srcDir .= ".{$moreDir}";
            }
        }
        return $srcDir;
    }


    /**
     * 获取Plu路径
     * @return string
     */
    private function getPluDir($data = null)
    {
        $dir = '';
        if (empty($data) || !is_array($data)) {
            $tc = $this->config;
        } else {
            $tc = $data;
        }
        $tcPlu = $tc['plu'];
        if (isset($tcPlu) && is_array($tcPlu) && is_string($tcPlu['dir'])) {
            $dir = trim($tcPlu['dir']);
            if (!empty($dir)) {
                return $dir;
            }
        }

        $dc = TphpConfig::$domain;
        if (is_array($dc['plu']) && is_string($dc['plu']['dir'])) {
            $dir = trim($dc['plu']['dir']);
        }
        return $dir;
    }

    /**
     * 获取Plu对象
     * @return null|\Tphp\Basic\Plugin\PluginClass
     */
    public function getPluObject()
    {
        // 强制设置插件返回
        $pluObj = null;
        $tc = $this->config;
        $tcPlu = $tc['plu'];
        if (isset($tcPlu) && is_array($tcPlu)) {
            if (is_object($tcPlu['caller'])) {
                $pluObj = self::getProperty($tcPlu['caller'], 'plu');
                if (is_object($pluObj)) {
                    $pluObj->setTpl($this);
                    return $pluObj;
                }
            }
        }

        // 如果是主路径，则返回主路径插件
        $dc = TphpConfig::$domainPath;
        if ($dc->isMainPath && !empty($dc->plu)) {
            return $dc->plu;
        }

        $pluObj = plu($this->getPluDir(), $this);
        return $pluObj;
    }

    /**
     * 返回JSON格式数据
     * @param $status
     * @param $msg
     * @param null $data
     * @return string
     */
    private function retJson($status, $msg, $data = null)
    {
        $ret = [];
        $ret['status'] = $status;
        $ret['msg'] = $msg;

        if (is_string($data)) {
            $data != "" && $ret['data'] = $data;
        } elseif (!empty($data)) {
            if (is_array($data)) {
                if (array_key_exists('status', $data) && array_key_exists('msg', $data)) {
                    $ret = $data;
                } else {
                    $ret['data'] = $data;
                }
            } else {
                $ret['data'] = $data;
            }
        }
        __exit(json_encode($ret, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 获取插件配置
     * @return null
     */
    public function getPluginsConfig($method = '')
    {
        if (is_null($this->config)) {
            $this->getDataConfig();
        }

        if (empty($this->config['plu'])) {
            return null;
        }

        $pc = $this->config['plu']['caller'];
        if (empty($method) || !is_string($method)) {
            return $pc;
        }

        if (method_exists($pc, $method)) {
            return call_user_func_array([$pc, $method], []);
        }

        return null;
    }

    /**
     * 创建目录
     * @param $path
     */
    private function mkDir($path)
    {
        if (is_readable($path)) return;
        $pLen = strlen($path);
        if ($pLen <= 0) return;
        $ti = 0;
        if ($path[0] == '/') {
            $ti = 1;
        } else {
            if ($pLen > 2 && $path[1] == ':') $ti = 3;
        }
        $bUrl = substr($path, 0, $ti);
        for ($i = $ti; $i < $pLen; $i++) {
            if (substr($path, $i, 1) == '\\' || substr($path, $i, 1) == '/') {
                $bUrl = $bUrl . substr($path, $ti, $i - $ti);
                if (!is_readable($bUrl)) mkdir($bUrl);
                for ($j = $i + 1; $j < strlen($path) - 1; $j++) {
                    if (substr($path, $j, 1) == '\\' || substr($path, $j, 1) == '/') {
                        $i++;
                    } else {
                        break;
                    }
                }
                $ti = $i;
            }
        }
    }

    /**
     * 文件缓存
     * @param $fileName
     * @return string
     */
    private function fileCachePath($fileName)
    {
        $checkStr = "EXITJSON";
        $srcFile = $this->dataPath . $fileName;
        $str = file_get_contents($srcFile);
        if (stripos($str, $checkStr) === false) return $srcFile;

        $dataCachePath = storage_path("framework/cache/tpl/");
        $this->mkDir($dataCachePath);
        $str = str_ireplace($checkStr, "return \$this->exitJson", $str);
        $filePath = $dataCachePath . $this->cacheIdMd5 . ".php";
        file_put_contents($filePath, $str);
        return $filePath;
    }

    /**
     * 打印数组并退出程序
     * @param $data
     */
    private function exitStr($data)
    {
        if (is_array($data)) {
            $show = json_encode($data, JSON_UNESCAPED_UNICODE);
        } elseif (is_object($data)) {
            try {
                $show = json_encode($data, JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                print_r($data);
                __exit();
            }
        } elseif (is_numeric($data)) {
            $show = $data . "";
        } else {
            $show = $data;
        }
        __exit($show);
    }

    /**
     * 解析初始化
     * @param $init
     * @return array
     */
    private function getDataInitHandle($init)
    {
        // 不为根节点则返回数据不退出
        if (!$this->isDomainStart) {
            if (!empty($this->exitJson)) {
                return [false, json_decode($this->exitJson, true)];
            }

            if (is_null($init)) {
                return [true, true];
            }

            return [false, $init];
        }

        // 如果是最外层则直接返回字符串数据
        if (!empty($this->exitJson)) {
            $this->exitStr($this->exitJson);
        }

        if ($init === false) return [false, false];

        if (!is_null($init)) {
            (is_string($init) || is_numeric($init) || is_object($init)) && $this->exitStr($init);
            if (is_array($init) && $init['status'] != 1) { //当状态不为1成功的时候返回
                return [false, $init];
            }
        }
        return [true, true];
    }

    /**
     * 获取方法类型
     * @return string
     */
    private function getTplMethodInitType()
    {
        $defaultType = 'tpl';
        $tplType = trim($this->tplType, " _");
        if ((empty($tplType) && $tplType != '0') || in_array(strtolower($tplType), ['html', 'htm'])) {
            $tplMethodType = $defaultType;
        } else {
            $tplMethodType = $tplType;
        }
        return $tplMethodType;
    }

    /**
     * 获取执行方法
     * @return array|bool
     */
    private function getTplMethodInit()
    {
        $dataPath = $this->dataPath;

        //初始化
        $methodPath = $dataPath . 'method.php';
        if (!file_exists($methodPath)) {
            $this->tplMethodType = $this->getTplMethodInitType();
            return null;
        }

        $method = $this->includeThisFile($methodPath);
        if (!is_object($method)) {
            $this->tplMethodType = $this->getTplMethodInitType();
            return null;
        }

        self::setProperty($method, 'tpl', $this);
        if (is_object($this->plu)) {
            self::setProperty($method, 'plu', $this->plu);
        }

        $this->tplMethodObject = $method;

        $this->tplMethodType = $this->getTplMethodInitType();

        $funName = "_" . $this->tplMethodType;

        return self::methodForClass("#" . $funName, $method, $funName)->auto(false)->invoke();
    }

    /**
     * 获取初始化数据
     * @return array|bool
     */
    public function getDataInit()
    {
        if ($this->dataIntFlag === true || rtrim($this->basePath, '\\/') == rtrim($this->tplPath, '\\/')) return true;

        $this->dataIntFlag = true;
        
        $this->tplMethodInit = $this->getTplMethodInit();

        $pc = $this->getPluginsConfig();

        $isSet = false;
        $isInit = false;
        if (is_object($pc) && method_exists($pc, '_init')) {
            if (!$isSet && is_null($this->config)) {
                $isSet = true;
                $this->getDataConfig();
            }
            $init = call_user_func_array([$pc, '_init'], [$this->tplMethodInit]);
            list($status, $ret) = $this->getDataInitHandle($init);
            if (!$status) {
                return [false, $ret];
            }
        }

        $dataPath = $this->dataPath;
        //初始化
        $_initPath = $dataPath . '_init.php';
        if (file_exists($_initPath)) {
            $_init = $this->includeThisFile($this->fileCachePath('_init.php'));
            if (!empty($_init)) {
                if (is_function($_init)) {
                    if (!$isSet && is_null($this->config)) {
                        $this->getDataConfig();
                    }
                    $init = $_init($this->tplMethodInit);
                    list($status, $ret) = $this->getDataInitHandle($init);
                    if (!$status) {
                        return [false, $ret];
                    }
                }
            }
        }

        return true;
    }

    /**
     * 获取数据信息
     * @param $type 类型
     * @param $config 配置参数
     * @return mixed
     */
    public function getDataInfo($type, $config, $default, $page = null)
    {
        if (in_array(strtolower($type), self::$dataTypeList)) {
            $sql = $this->getSqlInit();
            $type = strtolower($type);
            if ($type == 'sql') {
                return [false, $sql->select($config, $this->where, $page, $this)];
            } elseif ($type == 'sqlfind') {
                return [false, $sql->find($config, $this->where, $this)];
            } elseif (in_array($type, ['api', 'dir'])) {
                $setApiRun = $this->{self::API_RUN};
                if (is_function($setApiRun)) {
                    return [false, $setApiRun($type, $config)];
                }
                return [false, [0, 'Error']];
            }
        }
        return [true, [1, $default]];
    }

    /**
     * 获取配置文件信息
     * @param $dataType
     * @return array|mixed
     */
    private function getIniInfo($dataType, $iniFile = "")
    {
        $dataType = strtolower($dataType);

        $isDataIni = $this->dataIni['reset'];

        if ($isDataIni) {
            $ini = $this->dataIni['ini'];
        } else {
            //数据处理配置项
            if (empty($iniFile)) {
                $iniPath = $this->dataPath . 'ini.php';
            } else {
                $iniPath = $iniFile;
            }
            $pcIni = $this->getPluginsConfig('ini');
            if (empty($pcIni) || !is_array($pcIni)) {
                $pcIni = [];
            }
            $ini = [];
            if (file_exists($iniPath)) {
                $ini = $this->includeThisFile($iniPath);
                if (!is_array($ini)) {
                    $ini = [];
                }
            }
        }

        if (empty($ini)) {
            $ini = $pcIni;
        } elseif(!empty($pcIni)) {
            foreach ($pcIni as $key => $val) {
                if (is_integer($key)) {
                    $ini[] = $val;
                } elseif(is_array($val)) {
                    if (is_array($ini[$key])) {
                        foreach ($val as $k => $v) {
                            $ini[$key][$k] = $v;
                        }
                    } else {
                        $ini[$key] = $val;
                    }
                } else {
                    $ini[$key] = $val;
                }
            }
        }
        if (!empty($ini) && is_array($ini)) {
            if (in_array($dataType, self::$dataTypeList)) {
                if (!empty($ini)) {
                    $ini = $this->keyToLower($ini);
                    $sqlConfig = $ini['#sql'];
                    if (empty($sqlConfig)) {
                        unset($ini['#sql']);
                    } else {
                        $ini['#sql'] = $this->keyToLower($sqlConfig);
                    }
                }
            }
        } else {
            $ini = [];
        }
        return $ini;
    }

    /**
     * 重新设置配置数据信息
     * @param array $data
     * @param array $ini
     * @param array $page
     */
    public function resetDataIni($data=[], $ini=[], $page=[])
    {
        $this->dataIni['reset'] = true;

        if (!is_null($data) && is_array($data)) {
            $this->dataIni['data'] = $data;
        }

        if (!is_null($ini) && is_array($ini)) {
            $this->dataIni['ini'] = $ini;
        }

        if (!empty($page) && is_array($page)) {
            $this->page = $this->getPageInfoForUrl($page);
        }
    }

    /**
     * 数据处理
     * @param $data
     * @param $type
     * @param $config
     * @return mixed
     */
    public function getDataToIni($data, $type, $config)
    {
        $type = trim($type);
        $sqlConfig = $config['#sql'];
        if (empty($type)) {
            if (empty($sqlConfig)) {
                return apcu($config, $data);
            } else {
                return $data;
            }
        }

        $type = strtolower($type);
        $srcData = [];
        if (is_array($data)) {
            if (in_array($type, self::$dataTypeList)) {
                if (empty($sqlConfig)) return [$data, $data];
                if (in_array($type, ['sql', 'api', 'dir'])) {
                    $cConfig = [];
                    if (is_array($sqlConfig)) {
                        foreach ($sqlConfig as $key => $val) {
                            $cConfig[] = [$key, $val];
                        }
                    }
                    list($srcData, $data) = $this->getDataTypeSql($data, $cConfig);
                } elseif ($type == 'sqlfind') {
                    list($srcData, $data) = $this->getDataTypeSqlFind($data, $sqlConfig);
                }
                return [$srcData, $data];
            }
        }
        return [$srcData, apcu($config, $data)];
    }

    /**
     * 当碰到配置项无法处理的情况吓，数据使用原始代码处理（可选项）
     * @param $retData
     * @param array $params
     * @param bool $isDefault 是否默认
     * @param string $typeName set.php 和 src.php
     * @return array
     */
    private function getTypeData(&$retData, $params = [], $isDefault = false, $typeName = 'set')
    {
        $pc = $this->getPluginsConfig();
        if (is_object($pc) && method_exists($pc, $typeName)) {
            $retData = call_user_func_array([$pc, $typeName], [&$retData, &$params]);
        }
        $typePath = $this->dataPath . "{$typeName}.php";
        if (file_exists($typePath)) {
            $type = $this->includeThisFile($this->fileCachePath("{$typeName}.php"));
            if (!empty($type) && gettype($type) == 'object') {
                if (is_callable($type)) {
                    $retData = $type($retData, $params, $this->tplMethodInit);
                    if (!empty($this->exitJson)) return [1, $this->exitJson];
                } else {
                    $retData = $type;
                }
            }
        }

        if (!$isDefault && !is_array($retData)) {
            $retData = [];
        }
    }

    /**
     * 获取自定义data数据
     * @param $data
     * @return array
     */
    public function data($data)
    {
        return new TplData($data, $this);
    }

    /**
     * 处理data.php文件中的数据
     * @param $data
     * @return array
     */
    private function getDataHandle($data)
    {
        $params = [];
        isset($data['type']) && $dataType = $data['type']; //数据类型
        $srcData = [];
        $isDefault = false;
        if (empty($this->set)) {
            if (empty($data['config'])) { //数据配置项
                $retData = $data['default'];
            } else {
                list($isDefault, list($status, $retData, $fieldShow, $pageInfo, $sql)) = $this->getDataInfo($dataType, $data['config'], $data['default'], $this->page);
                if (!$status && is_string($retData)) return [0, $retData];
                !empty($sql) && $params['sql'] = $sql;
                !empty($fieldShow) && $params['fields'] = $fieldShow;
                $this->getTypeData($retData, $params, $isDefault, 'src');
                $this->fieldShow = $fieldShow;
                $fieldIsNum = [];
                if (!empty($fieldShow)) {
                    foreach ($fieldShow as $key => $val) {
                        if (strpos($val['type'], "int") !== false || strpos($val['type'], "float") !== false) {
                            $fieldIsNum[$key] = true;
                        } else {
                            $fieldIsNum[$key] = false;
                        }
                    }
                }
                $this->fieldIsNum = $fieldIsNum;
                $srcData = $retData;
                if (!$status) return [0, $retData];
            }
        } else { //当TPL已设置值的时候不执行数据处理操作
            $retData = $this->set;
            $this->getTypeData($retData, $params, $isDefault, 'src');
        }

        $ini = $this->getIniInfo($dataType);
        // 自定义配置文件设置
        $setApiIni = $this->{self::API_INI};
        if (is_function($setApiIni)) {
            $setApiIni($ini);
        }
        if (!empty($ini) && is_array($ini['#sql']) && !empty($ini['#sql'])) {
            list($srcData, $retData) = $this->getDataToIni($retData, $dataType, $ini);
        }

        $this->getTypeData($retData, $params, $isDefault);

        if (!empty($this->tplMethodObject)) {
            $tType = $this->tplMethodType;
            $runReset = self::methodForClass("#" . $tType, $this->tplMethodObject, $tType)->auto(false);
            if ($runReset->exists()) {
                $retData = $runReset->invokePointer($retData, $fieldShow, $this->tplMethodInit);
            }
        }
        return [1, $retData, $fieldShow, $pageInfo, $srcData];
    }

    /**
     * 获取数据
     * @return array
     */
    private function getData()
    {
        //设置TPL的HTML代码，如果该值一旦设置则直接返回html代码
        if ($this->html != '_#_#_') return [0, $this->html, self::$tmpField, self::$tmpPages];

        $di = $this->getDataInit();
        if ($di !== true) {
            return $di;
        }

        if ($this->dataFlag) {
            return $retInfo;
        }

        //先是数据配置读取
        $data = $this->getDataConfig(false);

        if (!is_function($this->{self::API_CONFIG})) {
            $this->setConfig();
        }

        if ($this->html != '_#_#_') {
            return [0, $this->html, self::$tmpField, self::$tmpPages];
        }

        //处理data.php文件中的数据
        return $this->getDataHandle($data);
    }

    /**
     * 获取分页信息
     * @param \Illuminate\Pagination\LengthAwarePaginator $page
     * @return array
     */
    public function getPageInfo(\Illuminate\Pagination\LengthAwarePaginator $page)
    {
        $pageArr = $page->toArray();
        return [
            'size' => $pageArr['per_page'],
            'listCount' => count($pageArr['data']),
            'total' => $pageArr['total'],
            'max' => $pageArr['last_page'],
            'now' => $pageArr['current_page']
        ];
    }

    /**
     * 获取模板数据
     * @return array
     */
    private function getRetData()
    {
        $gd = $this->getData();
        if ($gd === false) return false;
        list($status, $gData, $field, $pages, $srcData) = $gd;
        self::$tmpField = $field;
        self::$tmpPages = $pages;

        //设置返回数据到模板
        $retData['_'] = $gData;

        if (!empty($pages)) {
            //设置分页信息
            $retData['pageinfo'] = $this->getPageInfo($pages);
        }
        \Tphp\Basic\Sql\Page::$pages = $pages;

        //设置字段信息
        !empty($field) && $retData['field'] = $field;
        return [$status, $retData, $srcData];
    }

    /**
     * 获取原数据
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|string
     */
    private function runJson()
    {
        if (!is_dir($this->tplPath)) {
            return [false, $this->tplName . " Not Found"];
        }
        list($status, $retData) = $this->getRetData();
        if ($status) {
            if ($this->page['ispage']) {
                return [true, [
                    'page' => $retData['pageinfo'],
                    'list' => $retData['_']
                ]];
            } else {
                return [true, $retData['_']];
            }
        }

        $msgErr = $retData['_'];
        if (is_string($msgErr)) {
            return $msgErr;
        }
        return [false, $msgErr];
    }

    /**
     * 增加配置
     * @param $config
     */
    public function addConfig($config)
    {
        $tConfig = $this->config;
        if (empty($tConfig)) {
            $tConfig = $config;
        } elseif (is_array($config)) {
            $this->arrayMerge($tConfig, $config);
        }

        $view = $config['view'];
        if (!empty($view) && is_array($view)) {
            $tView = $this->viewData;
            if (empty($tView)) {
                $tView = [];
            }
            foreach ($view as $key => $val) {
                $tView[$key] = $val;
            }
            $this->viewData = $tView;
        }
        
        $this->config = $tConfig;
    }

    /**
     * 获取浏览器页面
     * @param array $info
     * @return array
     */
    private function getPageInfoForUrl($info = [])
    {
        if (!is_array($info)) {
            $info = [];
        }

        $isPage = $info['ispage'];

        $p = $_GET['p'];
        if (is_null($isPage)) {
            $isPage = isset($p) && is_numeric($p);
        }

        $info['pagesize'] <= 0 ? $pageSize = 20 : $pageSize = $info['pagesize']; //默认分页大小为20条记录
        $pageSizedef = $pageSize;
        if ($_GET['psize'] > 0) $pageSize = $_GET['psize'];
        return [
            'ispage' => $isPage,
            'pagesizedef' => $pageSizedef,
            'page' => $p,
            'pagesize' => $pageSize
        ];
    }

    /**
     * 设置配置信息
     */
    public function setConfig()
    {
        $info = $this->keyToLower($this->config);
        //数据处理路径
        !empty($info['data']) && $this->dataPath = $this->tplBase . $info['data'] . "/";
        //class合并路径
        !empty($info['class']) && $this->class = $info['class'];
        //数据库条件查询扩展（针对数据库查询有效）
        !empty($info['where']) && $this->where = $info['where'];
        //数据库分页处理（针对数据库查询有效）
        $isPage = $info['ispage']; //为True表示启用分页
        $this->page = $this->getPageInfoForUrl($info);

        //设置TPL值，如果该值一旦设置则不进行数据库查询处理
        empty($info['set']) ? $this->set = [] : $this->set = $info['set'];
        empty($info['html']) ? $this->html = '_#_#_' : $this->html = $info['html'];
    }

    /**
     * 获取缓存ID
     * @param string $cacheId
     * @return string
     */
    private function __getCacheId($cacheId = "")
    {
        $end = "";
        if (!empty($cacheId)) {
            $end = "_" . substr(md5($cacheId), 8, 8);
        }
        return substr($this->cacheIdMd5, 4, 8) . $end;
    }

    /**
     * 获取缓存信息
     * @param string $cacheId
     * @return mixed
     */
    public function getCache($cacheId = "")
    {
        return \Cache::get($this->__getCacheId($cacheId));
    }

    /**
     * 缓存数据
     * @param array $fun 可以设置为function
     * @param string $cacheId 扩展id
     * @param int $expire 过期时间
     * @return array
     */
    public function cache($fun = [], $cacheId = "", $expire = 60 * 60)
    {
        if (is_object($fun)) {
            $cId = $this->__getCacheId($cacheId);
            $data = \Cache::get($cId);
            if (empty($data)) {
                $data = $fun();
                if (!is_numeric($expire) && $expire < 0) {
                    $expire = 0;
                }
                \Cache::put($cId, $data, $expire);
            }
            return $data;
        } else {
            return $fun;
        }
    }

    /**
     * 清除Cache
     * @param string $cacheId
     */
    public function unCache($cacheId = "", $topBase = "")
    {
        if (empty($topBase)) {
            $md5 = $this->cacheIdMd5;
        } else {
            $realPath = $this->getRealTpl($topBase, $this->tplPath);
            $realPath = trim(trim(str_replace("\\", "/", $realPath), "/"));
            $md5 = md5($realPath);
        }
        $end = "";
        if (!empty($cacheId)) {
            $end = "_" . substr(md5($cacheId), 8, 8);
        }
        $cId = substr($md5, 4, 8) . $end;
        \Cache::forget($cId);
    }

    /**
     * 获取TPL类型
     * @param string $tplType
     * @return string
     */
    private function getMd5TplType($tplType = '')
    {
        if (in_array($tplType, ['html', 'htm'])) {
            $tplType = 'tpl';
        }
        return $tplType;
    }

    /**
     * JS 或 CSS 缓存处理
     * @param string $type
     * @return bool|string
     */
    private function getMd5info($type = 'css')
    {
        $class = self::$class;
        $retArr = [];
        $type != 'css' && $type = 'js';
        if ($type == 'css') {
            $arr = self::$css;
        } else {
            $arr = self::$js;
        }

        if (!is_array($arr)) {
            if (empty($arr)) {
                $arr = [];
            } else {
                $arr = [$arr];
            }
        }

        empty($arr) && $arr = [];

        $topTpl = self::$topTpl;
        $tplArr = [];
        $extArr = [];

        $plu = $this->plu;
        $static = '/static';
        if (!empty($plu) && !empty($plu->dir)) {
            $static .= "/plugins/{$plu->dir}/";
        }

        foreach ($arr as $val) {
            $val = trim($val);
            if (empty($val)) {
                continue;
            }
            if ($val[0] == '@') {
                // 外部链接以 @ 开头，可以是本页面的相对或绝对路径
                $_val = ltrim($val, '@');
                if (!empty($_val)) {
                    if ($val[1] == '@' && $type == 'js') {
                        $extArr[] = '@' . $_val;
                    } else {
                        $extArr[] = $_val;
                    }
                }
            } elseif (strpos($val, '://')) {
                // 外部链接以 http:// 或 https:// 开头
                $extArr[] = $val;
            } else {
                $val = $this->getRealTpl($val, $topTpl);
                if ($val != $topTpl) {
                    $tplArr[] = "{$val}:{$this->tplMethodType}";
                }
            }
        }
        if (!empty($extArr)) {
            $dfs = get_ob_start_value('static');
            if (empty($dfs)) {
                $dfs = [];
            }
            $dfs[$type] = $extArr;
            set_ob_start_value($dfs, 'static', true);
        }
        empty($class) && $class = [];

        ksort($class);
        if ($type == 'css') {
            foreach ($class as $key => $val) {
                $val = array_keys($val);
                sort($val);
                foreach ($val as $tmType) {
                    $tmType = $this->getMd5TplType($tmType);
                    if (is_file(Register::getViewPath($key . "/{$tmType}.{$type}", false)) || is_file(Register::getViewPath($key . "/{$tmType}.s{$type}", false))) {
                        $retArr[] = "{$key}:{$tmType}";
                    }
                }
            }
        } else {
            foreach ($class as $key => $val) {
                $val = array_keys($val);
                sort($val);
                foreach ($val as $tmType) {
                    $tmType = $this->getMd5TplType($tmType);
                    if (is_file(Register::getViewPath($key . "/{$tmType}.{$type}", false))) {
                        $retArr[] = "{$key}:{$tmType}";
                    }
                }
            }
        }

        $addI = 0;
        $md5 = "";
        $str = "";
        $md5Arr = array_merge($retArr, $tplArr);
        foreach ($md5Arr as $val) {
            $str .= "#" . $val;
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
        \Illuminate\Support\Facades\Cache::put("{$type}_t_{$md5}", $retArr, self::$cacheTime);
        \Illuminate\Support\Facades\Cache::put("{$type}_t_{$md5}_type", $tplArr, self::$cacheTime);
        \Illuminate\Support\Facades\Cache::put("{$type}_t_{$md5}_static", $static, self::$cacheTime);
        \Illuminate\Support\Facades\Cache::put("{$md5}_tpl", $topTpl, self::$cacheTime);
        return $md5;
    }

    public function getCss()
    {
        return $this->getMd5info();
    }

    public function getJs()
    {
        return $this->getMd5info('js');
    }

    /**
     * 跳转到指定页面
     * @param $url
     */
    public function redirect($url)
    {
        if ($url[0] == '/') {
            $argsUrl = $this->config['args']['url'];
            !empty($argsUrl) && $url = $argsUrl . $url;
        }
        redirect($url)->send();
    }

    /**
     * 执行类型，URL后罪名
     * @param null $tplType 后缀名
     */
    public function type($tplType = null)
    {
        return \Tphp\Basic\Tpl\Type::__init($tplType, $this->tplType);
    }
}
