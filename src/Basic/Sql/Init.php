<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Sql;

use DB;
use Tphp\Basic\Plugin\Init as PluginInit;
use Tphp\Basic\Tpl\Init as TplInit;

class Init
{
    private static $sqlInit = null;

    /**
     * @return Init
     */
    public static function __init() : Init
    {
        if (empty(self::$sqlInit)) {
            self::$sqlInit = new static();
        }
        return self::$sqlInit;
    }

    function __construct()
    {
        $this->lastSql = "";
    }

    /**
     * 执行数据库语句
     * @param $db
     * @param string $sqlStr
     * @return bool
     */
    public static function runDbExcute($db, $sqlStr = '')
    {
        $ret = false;
        if (is_array($sqlStr)) {
            foreach ($sqlStr as $s) {
                $s = trim($s);
                if (empty($s)) {
                    continue;
                }
                $ret = $db->statement($s);
            }
        } else {
            $ret = $db->statement($sqlStr);
        }
        return $ret;
    }

    /**
     * 错误消息提醒
     * @param $msg
     */
    private function __exitError($msg)
    {
        if (count($_POST) > 0) {
            EXITJSON(0, $msg);
        } else {
            if (!is_string($msg)) {
                $msg = json_encode($msg, true);
            }
            __exit($msg);
        }
    }

    /**
     * 获取链接名称
     * @param string $conn
     * @return bool|null|string
     */
    public function getConnectionName($conn = '')
    {
        return PluginInit::getConnectionName($conn);
    }

    public function getPluginTable($table = '', $conn = '', $obj = null)
    {
        if (is_function($table)) {
            $table = $table();
        }
        if (is_function($conn)) {
            $conn = $conn();
        }
        $isPlugin = false;
        $pluFields = [];
        if (empty($obj)) {
            $dataConfig = [];
        } else {
            $dataConfig = $obj->config['config'];
        }
        if (empty($conn)) {
            $conn = $dataConfig['conn'];
            if (empty($conn)) {
                $conn = config('database.default');
            }
        }
        $conn = $this->getConnectionName($conn);
        // 如果表为数组，则直接为插件形式
        if (is_array($table)) {
            $isPlugin = true;
            $pluObject = plu('', $obj)->model($table, $conn);
            if (empty($pluObject->table)) {
                $this->__exitError("Plugin: {$table} 不存在");
            }

            $table = $pluObject->table;
            if (!empty($pluObject->conn)) {
                $conn = $pluObject->conn;
            }
            $pluFields = $pluObject->getFieldNames();
            return [$table, $conn, $isPlugin, $pluFields];
        }

        $table = trim(strtolower($table));
        if (empty($table)) return [$table, $conn, $isPlugin, $pluFields];

        // 是否访问插件
        if ($table[0] == ':') {
            $table = trim(ltrim($table, ':'));
            if (empty($table)) return [$table, $conn, $isPlugin, $pluFields];
            $isPlugin = true;
        }
        if (empty($table) || !$isPlugin) {
            return [$table, $conn, $isPlugin, $pluFields];
        }

        // 插件支持
        $plu = $table;
        if (!empty($dataConfig['plu']) && strpos($plu, '=') === false) {
            $plu .= "==" . $dataConfig['plu'];
        }
        $pluObject = null;
        if (is_string($plu)) {
            $plu = trim($plu);
            if (!empty($plu)) {
                $pluId = substr(md5($plu . "_" . $conn), 8, 16);
                $pluObject = \Tphp\Config::$plugins['class'][$pluId];
                if (empty($pluObject)) {
                    $pluObject = plu('', $obj)->model($plu, $conn);
                    \Tphp\Config::$plugins['class'][$pluId] = $pluObject;
                }
            }
        } elseif (is_object($plu)) {
            $pluObject = $plu;
        }
        if (!empty($pluObject) && $pluObject instanceof \Tphp\Basic\Plugin\PluginModel) {
            if (empty($pluObject->table)) {
                $this->__exitError("Plugin: {$table} 不存在");
            }

            $table = $pluObject->table;
            if (!empty($pluObject->conn)) {
                $conn = $pluObject->conn;
            }
            $pluFields = $pluObject->getFieldNames();
        }
        return [$table, $conn, $isPlugin, $pluFields];
    }

    /**
     * 设置关联表名称
     * @param null $info
     * @param null $conn
     * @param null $table
     * @param null $obj
     */
    private function setExtendFieldName($info = null, $conn = null, $table = null, TplInit $obj = null)
    {
        if (empty($info[3])) {
            return;
        }

        $setApiField = $obj->{$obj::API_FIELD};
        if (is_function($setApiField)) {
            $setApiField($info[3], $conn, $table);
        }
    }

    /**
     * 获取链接转换
     * @param null $src
     * @return null
     */
    private function getDbSrc($src = '')
    {
        if (empty($src)) {
            return $src;
        }
        if (is_function($src)) {
            $src = $src();
        }
        if (is_array($src)) {
            $src0 = $src[0];
            if (is_function($src0)) {
                $src0 = $src0();
                $src[0] = $src0;
            }
            $src1 = $src[1];
            if (is_function($src1)) {
                $src1 = $src1();
            }
            if (!empty($src1)) {
                $src1 = $this->getConnectionName($src1);
                $src[1] = $src1;
            }
        } elseif (is_string($src)) {

        }
        return $src;
    }

    /**
     * 验证数据库是否可用
     * @param $data
     * @return array
     */
    private function checkDb(&$data, $obj = null)
    {
        $table = $data['table'];
        if (is_function($table)) {
            $table = $table();
        }
        is_string($table) && $table = strtolower($table);
        if (empty($table)) return [0, '表不能为空'];

        $conn = $data['conn'];
        list($table, $conn, $isPlugin) = $this->getPluginTable($table, $conn, $obj);
        $data['table'] = $table;

        $prefix = strtolower(trim(SqlCache::getDbConfig($conn, 'prefix')));

        $tbField = SqlCache::getTableInfo($conn, $table);
        if (empty($tbField)) return [0, $conn . "->" . $prefix . $table . '表不存在'];

        $data['conn'] = $conn;

        $field = $data['field'];
        $tb = [
            'name' => '',
            'field' => $tbField
        ];
        if (empty($field)) return [1, [
            'set' => $tbField,
            'show' => $tbField,
            'all' => $tbField
        ]];

        /**处理设置字段,字段信息如下：
         * 'field' => ['id', 'id', 'name',
         * [
         * ['order', 'id', 'sourceid', ['type'=>'order_type']],
         * ['member', 'id', 'mid', 'account']
         * ]...
         * ]*/

        $fieldStr = []; //字符串字段
        $fieldArr = []; //数组字段（高级处理字段）
        foreach ($field as $val) {
            if (is_string($val)) {
                $fieldStr[] = $val;
            } elseif (is_array($val) && count($val) > 0) {
                foreach ($val as $k => $v) {
                    if (!is_array($v)) {
                        continue;
                    }
                    $vTbs = $this->getDbSrc($v[0]);
                    // 插件转换
                    $pluTable = null;
                    $pluConn = null;
                    if (is_array($vTbs)) {
                        list($pluTable, $pluConn) = $vTbs;
                    } elseif (is_string($vTbs)) {
                        $pluTable = $vTbs;
                    }
                    if (empty($pluTable) || !is_string($pluTable)) {
                        continue;
                    }

                    if (is_string($pluTable)) {
                        $pluTable = trim($pluTable);
                    }
                    empty($pluConn) && $pluConn = $conn;

                    list($newTable, $newConn, $isPlugin) = $this->getPluginTable($pluTable, $pluConn, $obj);
                    if (empty($pluTable) || !$isPlugin) {
                        $this->setExtendFieldName($v, $newConn, $newTable, $obj);
                        continue;
                    }

                    if ($pluTable === $newTable) {
                        $this->setExtendFieldName($v, $newConn, $newTable, $obj);
                        continue;
                    }

                    if (empty($newConn)) {
                        $vTbs = $newTable;
                    } else {
                        $vTbs = [$newTable, $newConn];
                    }
                    $val[$k][0] = $vTbs;
                    $this->setExtendFieldName($val[$k], $newConn, $newTable, $obj);
                }
                $fieldArr[] = $val;
            }
        }
        $fieldStr = array_unique($fieldStr);

        $fieldShow = [];
        $fieldSet = [];
        foreach ($fieldStr as $val) {
            $val = strtolower(trim($val));
            if (!empty($tbField[$val])) {
                $fieldShow[$val] = $fieldSet[$val] = $tbField[$val];
            }
        }

        $fieldNext = [];
        $fieldAdds = [];
        $nexts = [
            $table => $tbField
        ];
        foreach ($fieldArr as $key => $val) {
            $ttb = $tb;
            $ttb['table'] = $table;

            $valLen = count($val) - 1;
            for ($i = $valLen; $i >= 0; $i--) {
                if (empty($val[$i][3])) {
                    unset($val[$i]);
                } else {
                    break;
                }
            }
            if (empty($val)) continue;

            foreach ($val as $k => $v) {
                if (!is_array($v)) return [0, $conn . "->{$table} 配置有误: {$k}=>{$v}"];
                $v[1] = strtolower(trim($v[1]));
                $v[2] = strtolower(trim($v[2]));
                $vTbs = $v[0];
                $vConn = $conn;

                if (is_array($vTbs)) {
                    if (empty($vTbs[1])) {
                        $vTb = strtolower(trim($vTbs[0]));
                        $vPrefix = $prefix;
                        $ttbNext = $tbField;
                    } else {
                        $vTb = strtolower(trim($vTbs[0]));
                        $vConn = $vTbs[1];
                        empty($vConn) && $vConn = config('database.default');

                        $vPrefix = strtolower(trim(SqlCache::getDbConfig($vConn, 'prefix')));
                        $ttbNext = SqlCache::getTableInfo($vConn, $vTb);
                        if (empty($ttbNext)) return [0, $vConn . "中" . $vPrefix . $vTb . '表不存在'];
                    }
                } else {
                    $vTb = strtolower(trim($vTbs));
                    $vPrefix = $prefix;
                    if (isset($nexts[$vTb])) {
                        $ttbNext = $nexts[$vTb];
                    } else {
                        $ttbNext = SqlCache::getTableInfo($vConn, $vTb);
                        if (empty($ttbNext)) return [0, $vConn . "中" . $vPrefix . $vTb . '表不存在'];
                        $nexts[$vTb] = $ttbNext;
                    }
                }

                if (!isset($ttbNext)) return [0, $vConn . "->{$vTb} 不存在"];
                if (!isset($ttbNext[$v[1]])) return [0, $vConn . "->{$vTb} 中的字段 {$v[1]} 不存在"];
                if (!isset($ttb['field'][$v[2]])) return [0, $vConn . "->{$ttb['table']} 中的字段 {$v[2]} 不存在"];
                if (!empty($v[3])) {
                    $tas = [];
                    if (is_string($v[3])) {
                        $vas = strtolower(trim($v[3]));
                        $tas[$vas] = $vas;
                    } else {
                        foreach ($v[3] as $kk => $vv) {
                            if (is_string($vv) || is_numeric($vv)) {
                                if (is_string($kk)) {
                                    $tas[strtolower(trim($kk))] = strtolower(trim($vv));
                                } else {
                                    $vas = strtolower(trim($vv));
                                    $tas[$vas] = $vas;
                                }
                            }
                        }
                    }

                    foreach ($tas as $kk => $vv) {
                        if (!isset($ttbNext[$kk])) return [0, $vConn . "->{$vTb} 中的字段 {$kk} 不存在"];
                        if (isset($fieldShow[$vv])) return [0, $vConn . "->{$vTb} 中字段 {$vv} 与主字段重复"];
                        if (isset($fieldAdds[$vv])) return [0, $vConn . "->{$vTb} 中字段 {$vv} 与其他字段重复"];
                        $fieldAdds[$vv] = $ttbNext[$kk];
                    }
                    $v[3] = $tas;
                }
                // 查询条件和排序
                if(!empty($v[4]) && is_array($v[4])) {
                    $v4 = $v[4];
                    $v4Where = $v4['where'];
                    if (is_array($v4Where)) {
                        list($status, $w) = $this->getWhere($v4Where, $ttbNext);
                        if (!$status) {
                            return [0, $vConn."->{$vTb} {$w}"];
                        }
                        $v[4]['where'] = $w;
                    } else {
                        unset($v[4]['where']);
                    }
                    $v4Order = $v4['order'];
                    if (is_array($v4Order)) {
                        $od = [];
                        foreach ($v4Order as $v4oKey => $v4oVal) {
                            if (is_string($v4oKey)) {
                                $vOrder = $v4oKey;
                                $vSort = $v4oVal;
                                if (!is_string($vSort)) {
                                    $vSort = 'asc';
                                }
                            } elseif(is_string($v4oVal)) {
                                $vOrder = $v4oVal;
                                $vSort = 'asc';
                            } else {
                                continue;
                            }
                            $vOrder = strtolower(trim($vOrder));
                            if (!empty($vOrder)) {
                                if (!isset($ttbNext[$vOrder])) return [0, $vConn . "->{$vTb} 中的字段 {$vOrder} 不存在"];
                                if (is_string($vSort)) {
                                    $vSort = strtolower(trim($vSort));
                                    if (!in_array($vSort, ['asc', 'desc'])) {
                                        $vSort = 'asc';
                                    }
                                } else {
                                    $vSort = 'asc';
                                }
                                $od[] = [$vOrder, $vSort];
                            }
                        }

                        if (empty($od)) {
                            unset($v[4]['order']);
                        } else {
                            $v[4]['order'] = $od;
                        }
                    } else {
                        unset($v[4]['order']);
                    }
                }
                if ($k == 0 && !empty($tbField[$v[2]])) {
                    empty($fieldSet[$v[2]]) && $fieldSet[$v[2]] = $tbField[$v[2]];
                }
                $ttb = [
                    'name' => '',
                    'field' => $ttbNext
                ];
                $ttb['table'] = $vTb;

                $val[$k] = $v;
            }
            $fieldNext[$key] = $val;
        }

        foreach ($fieldAdds as $key => $val) {
            $fieldShow[$key] = $val;
        }

        return [
            1, [
                'set' => $fieldSet,
                'show' => $fieldShow,
                'next' => $fieldNext,
                'all' => $tbField
            ]
        ];

    }

    /**
     * 设置KEY为小写
     * @param $data
     * @param $notin
     * @return array
     */
    private function setKeyLower($data)
    {
        if (empty($data) || is_string($data)) return $data;
        $newData = array();
        foreach ($data as $key => $val) {
            $newData[strtolower($key)] = $val;
        }
        return $newData;
    }

    /**
     * 条件语句处理
     * @param $where
     */
    public function getWhere($where, $fieldAll)
    {
        if (empty($where)) return [1, $where];
        if (is_string($where[0])) $where = [$where];
        $flag = 0;
        $childI = 0;
        $whereNew = [];
        ksort($where);
        foreach ($where as $key => $val) {
            if (is_string($val)) {
                if (strtolower(trim($val)) == "or") {
                    $flag++;
                    $whereNew[$flag] = 'or';
                    $flag++;
                }
            } elseif (is_array($val) && count($val) >= 2) {
                $isChild = true;
                if (is_string($val[0]) && is_string($val[1]) && strtolower(trim($val[1])) != 'or') {
                    $isChild = false;
                }
                if ($isChild) {
                    if (!empty($val) && is_array($val)) {
                        list($status, $vWhere) = $this->getWhere($val, $fieldAll);
                        if ($status) {
                            $whereNew[$flag]['child']["_#c#_{$childI}"] = $vWhere;
                            $childI++;
                        }
                    }
                } else {
                    $f = strtolower(trim($val[1]));
                    $n = strtolower(trim($val[0]));
                    isset($val[2]) ? $v = $val[2] : $v = [];
                    if (is_string($v) || is_numeric($v)) {
                        $whereNew[$flag][$f][$n][] = "{$v}";
                    } else {
                        if ($f == 'between' || $f == 'notbetween') {
                            if (count($v) == 2 && $v[1] > $v[0]) {
                                $whereNew[$flag][$f][$n][] = $v;
                            } else {
                                return [0, "字段 {$n} 条件范围出错"];
                            }
                        } elseif ($f == 'null' || $f == 'notnull') {
                            $whereNew[$flag][$f][$n] = 'null';
                        } else {
                            foreach ($v as $vv) {
                                (is_string($vv) || is_numeric($vv)) && $whereNew[$flag][$f][$n][] = "{$vv}";
                            }
                        }
                    }
                }
            }
        }

        $fields = [];
        $whereRet = [];
        foreach ($whereNew as $key => $val) {
            if (is_string($val)) {
                $whereRet[$key] = $val;
                continue;
            }
            foreach ($val as $k => $v) {
                foreach ($v as $kk => $vv) {
                    if ($k == 'child') {
                        $whereRet[$key][$k][$kk] = $vv;
                    } else {
                        $fields[] = $kk;
                        if ($k == 'between' || $k == 'notbetween') {
                            $mins = [];
                            $maxs = [];
                            foreach ($vv as $vvv) {
                                $mins[] = $vvv[0];
                                $maxs[] = $vvv[1];
                            }
                            if ($k == 'between') {
                                $min = max($mins);
                                $max = min($maxs);
                            } else {
                                $min = min($mins);
                                $max = max($maxs);
                            }
                            if ($min <= $max) {
                                $whereRet[$key][$k][$kk] = [$min, $max];
                            } else {
                                return [1, -1];
                            }
                        } elseif ($k == 'column') {
                            $tp = strtolower(trim($vv[0]));
                            $tf = strtolower(trim($vv[1]));
                            if (!empty($tp) && !empty($tf)) {
                                if (!isset($fieldAll[$tf])) return [0, "条件字段 {$tf} 不存在"];
                                if (!in_array($tp, ['=', '<>', '>', '>=', '<', '<='])) return [0, "条件字段 {$tf} 判断语句错误"];
                                $whereRet[$key][$k][$kk] = [$tp, $tf];
                            }
                        } else {
                            if (is_array($vv)) {
                                $whereRet[$key][$k][$kk] = array_unique($vv);
                            } else {
                                $whereRet[$key][$k][$kk] = $vv;
                            }
                        }
                    }
                }
            }
        }

        $fields = array_unique($fields);
        foreach ($fields as $val) {
            if (!isset($fieldAll[$val])) return [0, "条件字段 {$val} 错误"];
        }
        return [1, $whereRet];
    }

    /**
     * 排序处理
     * @param $order
     * @param $fieldAll
     * @return array
     */
    public function getOrder($order, $fieldAll)
    {
        if (!is_array($order)) return [0, "数据库排序错误"];
        $orderRet = [];
        foreach ($order as $key => $val) {
            $key = strtolower(trim($key));
            if (!isset($fieldAll[$key])) return [0, "排序字段 {$key} 不存在"];
            $val = strtolower(trim($val));
            if (!in_array($val, ["asc", "desc"])) {
                $val = "asc";
            }
            $orderRet[$key] = $val;
        }
        return [1, $orderRet];
    }

    private function setWhereQuery(&$query, $where, $isOr = false)
    {
        if ($isOr) {
            $cmdW = 'orWhere';
            $cmdWi = 'orWhereIn';
            $cmdWni = 'orWhereNotIn';
            $cmdWbt = 'orWhereBetween';
            $cmdWnbt = 'orWhereNotBetween';
            $cmdWn = 'orWhereNull';
            $cmdWnn = 'orWhereNotNull';
            $cmdWc = 'orWhereColumn';
        } else {
            $cmdW = 'where';
            $cmdWi = 'whereIn';
            $cmdWni = 'whereNotIn';
            $cmdWbt = 'whereBetween';
            $cmdWnbt = 'whereNotBetween';
            $cmdWn = 'whereNull';
            $cmdWnn = 'whereNotNull';
            $cmdWc = 'whereColumn';
        }

        $keyFlags = [
            '=' => $cmdWi,
            '<>' => $cmdWni,
            'between' => $cmdWbt,
            'notbetween' => $cmdWnbt,
            'null' => $cmdWn,
            'notnull' => $cmdWnn,
            'column' => $cmdWc,
        ];
        foreach ($where as $key => $val) {
            $cmd = $keyFlags[$key];
            foreach ($val as $k => $v) {
                if (in_array($key, ['=', '<>', 'between', 'notbetween'])) {
                    //等于、不等于、区间之内、区间之外
                    $query->$cmd($k, $v);
                } elseif (in_array($key, ['null', 'notnull'])) {
                    //值为空、值不为空
                    $query->$cmd($k);
                } elseif ($key == 'column') { //判断两个字段是否符合条件$v[0]为=,>和<
                    $query->$cmd($k, $v[0], $v[1]);
                } elseif ($key == 'like') { //模糊查询
                    if (count($v) > 1) {
                        $query->$cmdW(function ($q) use ($v, $k, $key) {
                            foreach ($v as $vv) $q->where($k, $key, $vv);
                        });
                    } else {
                        foreach ($v as $vv) $query->$cmdW($k, $key, $vv);
                    }
                } elseif ($key == 'child') { //子查询
                    $ws = $v;
                    if (count($ws) > 1) {
                        $query->$cmdW(function ($q) use ($ws) {
                            $this->setWhereMod($q, $ws);
                        });
                    } else {
                        $this->setWhereMod($query, $ws, $isOr);
                    }
                } else {
                    if ($key == '>' || $key == '>=') {
                        $query->$cmdW($k, $key, max($v));
                    } elseif ($key == '<' || $key == '<=') {
                        $query->$cmdW($k, $key, min($v));
                    }
                }
            }
        }
    }

    /**
     * 条件构造查询
     * @param $db 数据库构造器
     * @param $where 条件查询
     */
    public function setWhereMod(&$db, $where, $isOr = false)
    {
        if (empty($where) || !is_array($where)) {
            return;
        }

        foreach ($where as $w) {
            if ($w == 'or') {
                $isOr = true;
                continue;
            }
            if ($isOr) {
                $cmd = 'orWhere';
            } else {
                $cmd = 'where';
            }
            if (count($w) > 1) {
                $db->$cmd(function ($q) use ($w) {
                    $this->setWhereQuery($q, $w);
                });
            } else {
                $this->setWhereQuery($db, $w, $isOr);
            }
            $isOr = false;
        }
    }

    /**
     * 获取分页信息
     * @param $page
     * @param $db
     */
    private function selectGetPageInfo($page, &$db)
    {
        $pageSize = $page['pagesize'];
        $p = $page['page'];
        $p <= 0 && $p = 1;
        $cot = 0;
        if ($p > 0) {
            $cot = $db->count();
            $pMaxF = $cot / $pageSize;
            $pMax = intval($pMaxF);
            $pMaxF > $pMax && $pMax++;
            $p > $pMax && $p = $pMax;
        }
        $pages = $db->paginate($pageSize, ['*'], 'p', $p);
        $pages->page = $p;
        $pages->pageSize = $pageSize;
        $pages->pageSizeDef = $page['pagesizedef'];
        $pages->count = $cot;
        return $pages;
    }


    /**
     * 获取主键
     * @param array $fieldAll
     * @return int|string
     */
    private function getPrimary($fieldAll = [])
    {
        $ret  = [];
        foreach ($fieldAll as $key => $val) {
            if ($val['key'] == 'PRI') {
                $ret[] = $key;
            }
        }

        return $ret;
    }

    /**
     * 查找数据库
     * @param $data
     * @param $field
     * @param $fieldAll
     * @param $where
     * @param array $whereAdd
     * @param array $page
     * @param array $order
     * @param $forceUpdate 强制更新
     * @return array
     */
    private function _select($data, $field, $fieldAll, $where, $whereAdd = [], $page = [], $order = [], $forceUpdate)
    {
        $isQuery = $this->isQuery;
        $pkName = '';
        if (!$isQuery) {
            $fieldArr = [];
            foreach ($field as $key => $val) {
                $fieldArr[] = $key;
                if (empty($pkName) && $val['key'] == 'PRI') {
                    $pkName = $key;
                }
            }
            if (empty($fieldArr)) return [0, "查询字段不能为空"];
        }
        $table = $data['table'];
        $conn = $data['conn'];
        if ($isQuery) {
            $dbc = DB::connection($conn);
            $db = $dbc->table($dbc->raw("(" . $data['query'] . ") as query_table"));
            $pages = $this->selectGetPageInfo($page, $db);
            $dbList = $db->get();
            $this->lastSql = $db->toSql();
            return [1, json_decode(json_encode($dbList), true), $pages];
        } else {
            $db = DB::connection($conn)->table($table);
            !empty($where) && $this->setWhereMod($db, $where);
            !empty($whereAdd) && $this->setWhereMod($db, $whereAdd);

            list($status, $search) = $this->getWhere($data['search'], $fieldAll);
            if ($status && !empty($search)) {
                $this->setWhereMod($db, $search);
            }

            $cData = [];
            $isAdd = false;
            $isOther = false;
            if (!empty($data['edit'])) {
                //编辑操作
                $cData = $data['edit'];
            } elseif (!empty($data['add'])) {
                //增加操作
                $cData = $data['add'];
                $isAdd = true;
            } else {
                $isOther = true;
            }

            if (!empty($cData) && !$isOther) {
                $newData = [];
                foreach ($cData as $key => $val) {
                    if (is_string($key)) {
                        if (is_array($val)) {
                            $v = json_encode($val, true);
                        } elseif (is_numeric($val) || is_string($val)) {
                            $v = $val;
                        } else {
                            continue;
                        }
                        $newData[$key] = $v;
                    }
                }
                if (!empty($newData)) {
                    try {
                        foreach ($newData as $ndKey => $ndVal) {
                            if ($ndVal == '') {
                                $newData[$ndKey] = null;
                            }
                        }
                        if ($isAdd) {
                            $this->getPrimary($fieldAll);
                            empty($pkName) && $pkName = 'id';
                            $pkValue = $db->insertGetId($newData);
                            if ($pkValue > 0) {
                                $this->setWhereMod($db, [['=' => [$pkName => [$pkValue]]]]);
                                return [1, json_decode(json_encode($db->get()), true)];
                            }

                            $pkList = $this->getPrimary($fieldAll);

                            if (empty($pkList)) {
                                return [1, "增加成功！"];
                            }

                            $dWhere = [];
                            foreach ($pkList as $pkKey) {
                                if(isset($newData[$pkKey])) {
                                    $dWhere[] = [$pkKey, "=", $newData[$pkKey]];
                                }
                            }
                        } elseif(!empty($where)) {
                            if ($forceUpdate) {
                                if ($db->count() > 0) {
                                    $db->update($newData);
                                } else {
                                    // 强制更新数据，不存在时新增列表
                                    $where0 = $where[0];
                                    $isInsert = false;
                                    if (!empty($where0) && !empty($where0['='])) {
                                        foreach ($where0['='] as $wKey => $wVal) {
                                            if (is_array($wVal) && !empty($wVal[0])) {
                                                $newData[$wKey] = $wVal[0];
                                                !$isInsert && $isInsert = true;
                                            }
                                        }
                                    }
                                    $isInsert && $db->insert($newData);
                                }
                            } else {
                                $db->update($newData);
                            }

                            $dWhere = $data['where'];
                            if (!empty($dWhere)) {
                                foreach ($dWhere as $dwKey => $dwVal) {
                                    if (is_array($dwVal) && count($dwVal) == 3) {
                                        list($dwKeyName, $dwFlag, $dwValue) = $dwVal;
                                        if (isset($newData[$dwKeyName])) {
                                            $dwValue = $newData[$dwKeyName];
                                        }
                                        $dWhere[$dwKey] = [$dwKeyName, $dwFlag, $dwValue];
                                    }
                                }
                            }
                        }

                        list($status, $where) = $this->getWhere($dWhere, $fieldAll);
                        if (!$status) return [$status, $where];
                        if ($where == -1) return [1, null];

                        // 重新设定查询条件
                        $db = DB::connection($conn)->table($table);
                        !empty($where) && $this->setWhereMod($db, $where);
                        !empty($whereAdd) && $this->setWhereMod($db, $whereAdd);

                        if ($isAdd) {
                            return [1, json_decode(json_encode($db->get()), true)];
                        }

                    } catch (\Exception $e) {
                        return [0, "ERROR: " . $e->getMessage() . "<BR>File: " . $e->getFile() . "<BR>Line: " . $e->getLine()];
                    }
                }
            }
            $db->select($fieldArr);
        }

        $isPage = $page['ispage'];
        $exportType = $_GET['_@export@_'];
        $pages = [];
        if ($exportType === 'all') {
            $sqlLimit = env('SQL_LIMIT', 10000);
            !is_numeric($sqlLimit) && $sqlLimit = 10000;
            $db->limit($sqlLimit);
        } elseif ($isPage) {
            $pages = $this->selectGetPageInfo($page, $db);
        } else {
            $limit = $data['limit'];
            $offset = $data['offset'];
            (empty($offset) || $offset <= 0) && $offset = 0;

            $limitDefault = 100;

            if (is_null($limit)) {
                $limit = $limitDefault;
            } elseif (is_string($limit)) {
                $limit = intval($limit);
            }

            if (!is_integer($limit)) {
                $limit = $limitDefault;
            }
            
            if ($limit != -1) {
                (empty($limit) || $limit <= 0) && $limit = 0;
                $db->limit($limit)->offset($offset);
            }
        }
        if (!empty($order)) {
            foreach ($order as $key => $val) {
                $db->orderBy($key, $val);
            }
        }
        $this->lastSql = $db->toSql();
//        dump($this->lastSql );
        return [1, json_decode(json_encode($db->get()), true), $pages];
    }

    /**
     * 设置字段高级关联值
     * @param $list
     * @param $fieldNext
     */
    private function setFieldList($config, &$list, $fieldNext)
    {
        $fields = [];
        foreach ($fieldNext as $val) {
            !empty($val[0][2]) && $fields[] = $val[0][2];
        }
        $fields = array_unique($fields);

        $data = [];
        foreach ($list as $key => $val) {
            foreach ($fields as $v) {
                $data[$v][] = $val[$v];
            }
        }

        foreach ($data as $key => $val) {
            $data[$key] = array_unique($val);
        }
        $conn = $config['conn'];
        $fieldTops = [];
        foreach ($fieldNext as $val) {
            foreach ($val as $v) {
                if (is_string($v[2]) && !isset($fieldTops[$v[2]])) {
                    $fieldTops[$v[2]] = true;
                }
                break;
            }
        }
        $fieldSets = [];
        foreach ($list as $val) {
            foreach ($fieldTops as $fKey => $fVal) {
                $fieldSets[$fKey][] = $val[$fKey];
            }
        }
        foreach ($fieldSets as $key => $val) {
            $fieldSets[$key] = array_unique($val);
        }
        $listAdds = [];
        $nextKvNames = [];
        foreach ($fieldNext as $val) {
            $i = 0;
            $topField = '';
            foreach ($val as $v) {
                if ($i > 0) {
                    if (isset($val[$i - 1][3])) {
                        if (!is_array($val[$i - 1][4])) {
                            $val[$i - 1][4] = [];
                        }
                    }
                    $val[$i - 1][4]['next'] = $v[2];
                } else {
                    $topField = $v[2];
                }
                $i++;
            }
            $fSets = $fieldSets;
            $next = [];
            foreach ($list as $lk => $lv) {
                $next[$lv[$topField]] = $lv[$topField];
            }
            foreach ($val as $v) {
                if (empty($next)) {
                    break;
                }

                $v1 = $v[1];
                $v2 = $v[2];
                $fKvs = $fSets[$v2];
                if (!isset($fKvs)) {
                    continue;
                }
                $vTable = $v[0];
                $vConn = $conn;
                if (is_array($vTable)) {
                    list($vTable, $vConn) = $vTable;
                    empty($vConn) && $vConn = $conn;
                }
                $v3 = $v[3];
                $tfKv = [];
                $tfVals = [];
                if (is_string($v3)) {
                    $tfKv[$v3] = $v3;
                    $tfVals[] = $v3;
                } elseif (is_array($v3)) {
                    foreach ($v3 as $v3k => $v3v) {
                        if (is_int($v3k)) {
                            $tfKv[$v3v] = $v3v;
                            $tfVals[] = $v3v;
                        } elseif (is_string($v3v)) {
                            $tfKv[$v3k] = $v3v;
                            $tfVals[] = $v3k;
                        }
                    }
                }
                foreach ($tfKv as $_v) {
                    $nextKvNames[] = $_v;
                }
                $tfValsSelect = $tfVals;
                $tfValsSelect[] = $v1;
                $v4 = $v[4];
                $v4Where = [];
                $v4Order = [];
                $v4Next = '';
                if(!empty($v4) && is_array($v4)){
                    if (is_array($v4['where'])){
                        $v4Where = $v4['where'];
                    }
                    if (is_array($v4['order'])){
                        $v4Order = $v4['order'];
                    }
                    if (is_string($v4['next'])){
                        $v4Next = trim($v4['next']);
                    }
                }
                if (!empty($v4Next)) {
                    $tfValsSelect[] = $v4Next;
                }
                try{
                    $vDb = DB::connection($vConn)->table($vTable)->whereIn($v1, $fKvs)->select($tfValsSelect);
                    if (!empty($v4Where)) {
                        $this->setWhereMod($vDb, $v4Where);
                    }
                    if (!empty($v4Order)) {
                        foreach ($v4Order as $v4V) {
                            $vDb->orderBy($v4V[0], $v4V[1]);
                        }
                    }
                    $vList = $vDb->get();
                } catch (\Exception $e){
                    // 如果$fKvs中不支持字符串，则使用数字或小数类型搜索
                    $numFKvs = [];
                    foreach ($fKvs as $fKv){
                        if(is_numeric($fKv)){
                            $numFKvs[] = $fKv;
                        }
                    }
                    if(empty($numFKvs)){
                        $vList = [];
                    }else{
                        try {
                            $vDb = DB::connection($vConn)->table($vTable)->whereIn($v1, $numFKvs)->select($tfValsSelect);
                            if (!empty($v4Where)) {
                                $this->setWhereMod($vDb, $v4Where);
                            }
                            if (!empty($v4Order)) {
                                foreach ($v4Order as $v4V) {
                                    $vDb->orderBy($v4V[0], $v4V[1]);
                                }
                            }
                            $vList = $vDb->get();
                        } catch (\Exception $e){
                            // 如果$fKvs中不支持小数，则使用数字类型搜索
                            $intFKvs = [];
                            foreach ($numFKvs as $num){
                                if(strpos($num, ".") === false){
                                    $intFKvs[] = $num;
                                }
                            }
                            if(empty($intFKvs)){
                                $vList = [];
                            }else{
                                $vDb = DB::connection($vConn)->table($vTable)->whereIn($v1, $intFKvs)->select($tfValsSelect);
                                if (!empty($v4Where)) {
                                    $this->setWhereMod($vDb, $v4Where);
                                }
                                if (!empty($v4Order)) {
                                    foreach ($v4Order as $v4V) {
                                        $vDb->orderBy($v4V[0], $v4V[1]);
                                    }
                                }
                                $vList = $vDb->get();
                            }
                        }
                    }
                }

                if (count($vList) <= 0) {
                    break;
                }

                $dNext = [];
                foreach ($next as $nk=>$nv){
                    $dNext[$nv] = $nk;
                }

                if (!empty($v4Next)) {
                    $fSets[$v4Next] = [];
                    foreach ($vList as $vv){
                        if (isset($vv->$v4Next)) {
                            $fSets[$v4Next][] = $vv->$v4Next;
                        }
                    }
                }

                foreach ($vList as $vv){
                    foreach ($tfKv as $tkk=>$tvv) {
                        if(isset($dNext[$vv->$v1])) {
                            $listAdds[$topField][$tvv][$dNext[$vv->$v1]] = $vv->$tkk;
                        }
                    }
                }

                // 下一组参数传递
                $oldNext = $next;
                $next = [];
                if (!empty($v4Next)) {
                    foreach ($vList as $vv){
                        if (isset($oldNext[$vv->$v1])) {
                            $next[$vv->$v1] = $vv->$v4Next;
                        }
                    }
                }
            }
        }

        foreach ($list as $key => $val) {
            foreach ($listAdds as $k => $v) {
                foreach ($v as $kk => $vv) {
                    $list[$key][$kk] = $vv[$val[$k]];
                }
            }
        }
        $nextKvNames = array_unique($nextKvNames);
        foreach ($list as $key => $val) {
            foreach ($nextKvNames as $v) {
                if (!isset($val[$v])) {
                    $list[$key][$v] = '';
                }
            }
        }
    }

    /**
     * 查找表数据
     * @param $config
     * @return array
     */
    private function getSelectData($config, $tplWhere = [], $page = [], $obj = null)
    {
        $query = $config['query'];
        $isQuery = false;
        if (!empty($query) && is_string($query)) {
            $query = trim($query);
            !empty($query) && $isQuery = true;
        }
        $this->isQuery = $isQuery;

        if (!$isQuery) {
            if (isset($config['where']) && !is_array($config['where'])) {
                return [1, []];
            }
            //验证数据库是否正确
            list($status, $field) = $this->checkDb($config, $obj);
            if (!$status) return [$status, $field];
            list($status, $where) = $this->getWhere($config['where'], $field['all']);
            if (!$status) return [$status, $where];
            if ($where == -1) return [1, null];
            if (!empty($tplWhere)) {
                list($status, $whereAdd) = $this->getWhere($tplWhere, $field['all']);
                if (!$status) return [$status, $whereAdd];
            }

            if (!empty($config['order'])) {
                list($status, $order) = $this->getOrder($config['order'], $field['all']);
                if (!$status) return [$status, $order];
            }
        }
        $forceUpdate = $config['forceupdate'];
        if (!is_bool($forceUpdate)) {
            $forceUpdate = false;
        }
        list($status, $list, $pageInfo) = $this->_select($config, $field['set'], $field['all'], $where, $whereAdd, $page, $order, $forceUpdate);
        if (!$status) return [$status, $list];
        
        $fieldNext = $field['next'];
        $fieldShow = $field['show'];
        if (empty($list) || empty($fieldNext)) return [1, $list, $fieldShow, $pageInfo, $this->lastSql];

        $this->setFieldList($config, $list, $fieldNext);
        return [1, $list, $fieldShow, $pageInfo, $this->lastSql];
    }

    /**
     * 查找数据库列表
     * @param $config
     * @return array
     */
    public function select($config, $where = [], $page = [], $obj = null)
    {
        $config = $this->setKeyLower($config);
        $ret = $this->getSelectData($config, $where, $page, $obj);
        if ($ret[0] == 0 && count($_POST) > 0) {
            EXITJSON(0, $ret[1]);
        }
        return $ret;
    }

    /**
     * 查找数据库一个列表
     * @param $config
     * @return array
     */
    public function find($config, $where = [], $obj = null)
    {
        $config = $this->setKeyLower($config);
        if (empty($config['offset']) || $config['offset'] < 0) $config['offset'] = 0;
        $config['limit'] = 1;
        list($status, $list, $fieldShow) = $this->getSelectData($config, $where);
        if ($status == 0 && count($_POST) > 0) {
            EXITJSON(0, $list);
        }
        if (is_string($list)) {
            $retList = $list;
        } else {
            $retList = $list[0];
        }
        return [$status, $retList, $fieldShow, [], $this->lastSql];
    }
}
