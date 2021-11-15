<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl\Init;
use Tphp\Basic\Sql\SqlCache;

/**
 * 数据库类
 * Trait Method
 * @package Tphp\Basic\Tpl\Init
 */
trait Sql
{
    /**
     * 获取数据库表字段信息
     * @param string $conn 数据库配置名称
     * @param int $table 表名称
     * @param bool $isUpdate
     * @return array|mixed|null
     */
    public function tableInfo($conn = "", $table = "", $isUpdate = false)
    {
        return SqlCache::getTableInfo($conn, $table, $isUpdate);
    }

    /**
     * 获取SQL实例实现
     * @return |null
     */
    private function getSqlInit()
    {
        return \Tphp\Basic\Sql\Init::__init();
    }

    /**
     * 操作其他规则数据库，自定义数据查询
     * @param string $table
     * @param string $conn
     * @return mixed
     */
    public function db($table = "", $conn = "")
    {
        $data = $this->getDataConfig();
        if (in_array(strtolower($data['type']), self::$dataTypeList)) {
            if (empty($conn)) {
                $conn = $data['config']['conn'];
                if (empty($conn)) {
                    $conn = config('database.default');
                }
            }
            if ($table !== false && empty($table)) {
                $table = $data['config']['table'];
            }
        }

        list($table, $conn) = $this->getSqlInit()->getPluginTable($table, $conn, $this);

        if (empty($table) || $table === false) {
            $db = \DB::connection($conn);
        } else {
            $db = \DB::connection($conn)->table($table);
        }
        return $db;
    }

    /**
     * 数据库连接
     * @param string $conn
     * @return mixed
     */
    public function dbSelect($sqlStr, $conn = "", $isLimit = true)
    {
        $data = $this->getDataConfig();
        if (in_array(strtolower($data['type']), self::$dataTypeList)) {
            empty($conn) && $conn = $data['config']['conn'];
        }

        if (empty($conn)) {
            $conn = config('database.default');
        }
        $conn = $this->getSqlInit()->getConnectionName($conn);
        $maxRow = 1000;
        $sqlType = strtolower(trim(config("database.connections.{$conn}.driver")));
        if ($isLimit) {
            if (in_array($sqlType, ['mysql', 'sqlite', 'pgsql'])) {
                if (stripos($sqlStr, " limit ") <= 0) {
                    $sqlStr = $sqlStr . " limit {$maxRow} offset 0";
                }
            } elseif ($sqlType == 'sqlsrv') {
                if (stripos($sqlStr, " top ") <= 0) {
                    $sqlStr = str_ireplace("select", "select top {$maxRow}", $sqlStr);
                }
            }
        }

        try {
            $list = \DB::connection($conn)->select($sqlStr);
            $listArr = json_decode(json_encode($list), JSON_UNESCAPED_UNICODE);
            foreach ($listArr as $key => $val) {
                $listArr[$key] = $this->keyToLower($val);
            }
            return $listArr;
        } catch (Exception $e) {
            return $e->getPrevious()->errorInfo[2];
        }
    }

    /**
     * 设置条件查询
     * @param $db
     * @param $where
     */
    public function setWhere(&$db, $where = [], $fieldAll = [])
    {
        $sql = $this->getSqlInit();
        list($status, $w) = $sql->getWhere($where, $fieldAll);
        !$status && $w = [];
        $sql->setWhereMod($db, $w);
    }

    /**
     * 字符规则替换
     * @param $tmpConf
     */
    private function getDataTypeSqlStrReplace(&$tmpConf)
    {
        $fChr = '_=$#$=_';
        $dub = "#dub*";
        $flags = [
            "\n" => "{$fChr}n",
            "\t" => "{$fChr}t",
            "\r" => "{$fChr}r",
            "\e" => "{$fChr}e",
            "\f" => "{$fChr}f",
            "\v" => "{$fChr}v",

            "\\n" => "{$fChr}n",
            "\\t" => "{$fChr}t",
            "\\r" => "{$fChr}r",
            "\\e" => "{$fChr}e",
            "\\f" => "{$fChr}f",
            "\\v" => "{$fChr}v",

            "\\{$dub}" => "{$fChr}{$dub}",
            "\/" => "{$fChr}/",
        ];
        foreach ($flags as $fk => $fv) {
            $tmpConf = str_replace($fk, $fv, $tmpConf);
        }
        $tmpConf = str_replace($dub, "\"", $tmpConf);
        $tmpConf = str_replace("#in*", "{$fChr}\"", $tmpConf);
        $tmpConf = str_replace("\\", "\\\\", $tmpConf);
        $tmpConf = str_replace($fChr, "\\", $tmpConf);
    }

    /**
     * APCU运算转换
     * @param $funAddr
     * @param $jConfs
     */
    private function getDataTypeSqlConfigChg(&$funAddr, $jConfs, $inData)
    {
        !is_array($inData) && $inData = [];
        if (is_array($funAddr)) {
            return;
        }
        $fTrim = trim($funAddr);
        if (is_null($funAddr) || strlen(trim($fTrim)) < 18) {
            return;
        }
        if (isset($jConfs[$fTrim])) {
            eval("\$funAddr = {$jConfs[$fTrim]};");
            return;
        }
        $lPos = strpos($fTrim, "#");
        if ($lPos === false) {
            return;
        }

        $rPos = strrpos($fTrim, "#");
        if ($rPos <= $lPos) {
            return;
        }

        foreach ($jConfs as $key => $val) {
            if (strpos($fTrim, $key) !== false) {
                $obj = null;
                eval("\$obj = {$val};");
                if (is_null($obj)) {
                    $obj = "";
                } elseif (is_array($obj)) {
                    $obj = json_encode($obj, JSON_UNESCAPED_UNICODE);
                }
                $funAddr = str_replace($key, $obj, $funAddr);
            }
        }
    }

    /**
     * 运行外围运算 以':'为前缀
     * @param $cKey
     * @param $cVal
     * @param $selfFlag
     * @param $newData
     */
    private function getDataTypeSqlConfigOut($cKey, $cVal, $selfFlag, $jConfs, &$newData)
    {
        $cArr = explode(".", $cKey);
        $cInto = $newData;
        foreach ($cArr as $ck => $ca) {
            $tca = ltrim($ca);
            if ($tca[0] == '#') {
                $tca = trim(substr($tca, 1));
                if ($tca == '') {
                    $cArr[$ck] = -1;
                } elseif ((preg_match("/^[1-9][0-9]*$/", $tca) || $tca == '0') && $tca >= 0) {
                    $cArr[$ck] = intval($tca);
                }
            }
        }

        //$val为$tmpConf内部运算
        $val = $newData;
        if (trim($cKey) == "") {
            $cInto = $newData;
        } else {
            foreach ($cArr as $ca) {
                if (empty($cInto) || $ca === -1) {
                    $cInto = "";
                    break;
                } else {
                    $val = $cInto;
                    if (is_array($val)) {
                        $cInto = $cInto[$ca];
                    } else {
                        $cInto = "";
                        break;
                    }
                }
            }
        }

        $cExp = [];
        foreach ($cArr as $ca) {
            if ($ca === -1) {
                $cExp[] = "[]";
            } elseif (is_int($ca)) {
                $cExp[] = "[{$ca}]";
            } else {
                $ca = str_replace("'", "\\'", $ca);
                $cExp[] = "['{$ca}']";
            }
        }
        $cExpstr = implode("", $cExp);
        empty($cInto) && $cInto = "";


        $tmpConf = "";
        eval("\$tmpConf = \"{$cVal}\";");
        $tmpConf = str_replace($selfFlag, $cInto, $tmpConf);
        $this->getDataTypeSqlStrReplace($tmpConf);
        $tc = json_decode($tmpConf, true);

        foreach ($tc as $tcKey => $tcVal) {
            foreach ($tcVal as $k => $v) {
                if ($k === 0 && $v === 'unset') {
                    eval("unset(\$newData{$cExpstr});");
                    continue;
                } else {
                    $this->getDataTypeSqlConfigChg($tc[$tcKey][$k], $jConfs, $val);
                }
            }
        }

        try {
            $cv = apcu($tc, $cInto);
        } catch (Exception $e) {
            $cv = $e->getMessage();
        }

        if (trim($cKey) == "") {
            $newData = $cv;
            return;
        }
        if (!is_null($cv)) {
            if ($cExpstr != '') {
                $cExplen = count($cExp);
                if ($cExplen > 0) {
                    unset($cExp[$cExplen - 1]);
                    $isReplace = true;
                    foreach ($cExp as $ce) {
                        if ($ce == '[]') {
                            $isReplace = false;
                            break;
                        }
                    }
                    if ($isReplace) {
                        $c2 = implode("", $cExp);
                        $nPrev = NULL;
                        eval("!isset(\$newData{$c2}) && \$newData{$c2} = []; \$nPrev = \$newData{$c2};");
                        if (!is_array($nPrev)) {
                            eval("\$newData{$c2} = [];");
                        }
                        eval("\$newData{$cExpstr} = \$cv;");
                    }
                }
            }
        }
    }

    /**
     * 内围运算
     * @param $cKey
     * @param $cVal
     * @param $selfFlag
     * @param $newData
     */
    private function getDataTypeSqlConfigIn($cKey, $cVal, $selfFlag, $jConfs, &$newData)
    {
        foreach ($newData as $nKey => $nVal) {
            if (is_array($nVal)) {
                $tmpConf = "";
                eval("\$tmpConf = \"{$cVal}\";");
                $tmpConf = str_replace($selfFlag, $nVal[$cKey], $tmpConf);
                $this->getDataTypeSqlStrReplace($tmpConf);
                $tc = json_decode($tmpConf, true);

                foreach ($tc as $tcKey => $tcVal) {
                    foreach ($tcVal as $k => $v) {
                        if ($k === 0 && $v === 'unset') {
                            unset($nVal[$cKey]);
                            continue;
                        } else {
                            $this->getDataTypeSqlConfigChg($tc[$tcKey][$k], $jConfs, $nVal);
                        }
                    }
                }

                try {
                    $v_v = apcu($tc, $nVal[$cKey]);
                    if (!is_null($v_v)) {
                        $nVal[$cKey] = $v_v;
                    }
                } catch (Exception $e) {
                    $nVal[$cKey] = $e->getMessage();
                }
                $newData[$nKey] = $nVal;
            }
        }
    }

    /**
     * Sql类型数据转换
     * @param $data
     * @param $config
     * @return array
     */
    public function getDataTypeSql($data, $config)
    {
        $newData = [];
        $srcData = [];
        foreach ($data as $key => $val) {
            $newData[$key] = $this->keyToLowerOrNull($val);
            $srcData[$key] = $this->keyToLowerOrNull($val);
        }

        if (empty($config)) return $newData;

        $selfFlag = "_#$#_";
        $jsonConfig = json_encode($config, JSON_UNESCAPED_UNICODE);
        $jsonConfig = str_replace("_[]_", $selfFlag, $jsonConfig);


        $jcLen = strlen($jsonConfig);
        $jcBool = false;
        $jcKeys = [];
        $jcStr = "";
        for ($i = 0; $i < $jcLen; $i++) {
            if ($jsonConfig[$i] == '_' && $jsonConfig[$i + 1] == '[') {
                $jcBool = true;
                $i = $i + 2;
            } elseif ($jsonConfig[$i] == ']' && $jsonConfig[$i + 1] == '_') {
                $jcBool = false;
                $jcKeys["_[{$jcStr}]_"] = $jcStr;
                $jcStr = "";
            }
            if ($jcBool) {
                $jcStr .= $jsonConfig[$i];
            }
        }

        //值字符串替换
        $jcValues = [];
        foreach ($jcKeys as $key => $keyName) {
            $lkn = ltrim($keyName);
            if ($lkn == '') {
                $jcValues[$key] = "{\$val['{$keyName}']}";
                continue;
            }

            if ($lkn[0] == ':') {
                $keyName = substr($lkn, 1);
                if (trim($keyName) == '') {
                    $jcValues[$key] = "\$newData";
                    continue;
                }
            }
            $keyName = str_replace("'", "\\'", $keyName);
            $kArr = explode(".", $keyName);
            $kStep = [];
            foreach ($kArr as $ka) {
                $kaL = ltrim($ka);
                if ($kaL != '' && $kaL[0] == '#') {
                    $kaLIn = trim(substr($kaL, 1));
                    if ($kaLIn != '' && (preg_match("/^[1-9][0-9]*$/", $kaLIn) || $kaLIn == '0') && $kaLIn >= 0) {
                        $kStep[] = "[{$kaLIn}]";
                        continue;
                    }
                }
                $kStep[] = "['{$ka}']";
            }

            $keyStr = implode("", $kStep);
            if (substr($key, 0, 3) == '_[:') {
                $jcValues[$key] = "\$newData{$keyStr}";
            } else {
                $jcValues[$key] = "\$inData{$keyStr}";
            }
        }

        $jConfs = [];
        foreach ($jcValues as $key => $val) {
            $keyMd5 = "#" . substr(md5($key), 8, 16) . "#";
            $jConfs[$keyMd5] = $val;
            $jsonConfig = str_replace($key, $keyMd5, $jsonConfig);
        }

        $config = json_decode($jsonConfig, true);
        foreach ($config as $_key => $_val) {
            list($key, $val) = $_val;
            $vStr = json_encode($val, JSON_UNESCAPED_UNICODE);
            $vStr = str_replace("\"", "#dub*", $vStr);
            $tKey = ltrim($key);
            if ($tKey[0] == ':') {
                $tKey = substr($tKey, 1);
                $this->getDataTypeSqlConfigOut($tKey, $vStr, $selfFlag, $jConfs, $newData);
            } else {
                if ($tKey[0] == '\\' && $tKey[1] == ':') {
                    $vStr = substr($vStr, 1);
                }
                $this->getDataTypeSqlConfigIn($key, $vStr, $selfFlag, $jConfs, $newData);
            }
        }
        return [$srcData, $newData];
    }

    /**
     * Sql类型数据转换
     * @param $data
     * @param $config
     * @return array
     */
    public function getDataTypeSqlFind($data, $config)
    {
        $newData = $this->keyToLower($data);
        $srcData = $this->keyToLowerOrNull($data);
        if (empty($config)) return $newData;

        $selfFlag = "_#$#_";
        $jsonConfig = json_encode($config, JSON_UNESCAPED_UNICODE);
        $jsonConfig = str_replace("_[]_", $selfFlag, $jsonConfig);
        $jsonConfig = str_replace("_[", "{\$newData['", $jsonConfig);
        $jsonConfig = str_replace("]_", "']}", $jsonConfig);
        $config = json_decode($jsonConfig, true);
        foreach ($config as $key => $val) {
            $vStr = json_encode($val, JSON_UNESCAPED_UNICODE);
            $vStr = str_replace("\"", "#dub*", $vStr);
            $config[$key] = $vStr;
        }

        foreach ($config as $key => $val) {
            $tmpConf = "";
            eval("\$tmpConf = \"{$val}\";");
            $tmpConf = str_replace($selfFlag, $newData[$key], $tmpConf);
            $this->getDataTypeSqlStrReplace($tmpConf);
            $tc = json_decode($tmpConf, true);
            try {
                $v_v = apcu($tc, $newData[$key]);
                if (!is_null($v_v)) {
                    $newData[$key] = $v_v;
                }
            } catch (Exception $e) {
                $newData[$key] = $e->getMessage();
            }
        }
        return [$srcData, $newData];
    }
}
