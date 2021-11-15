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
use Illuminate\Support\Facades\Cache;
use Tphp\Basic\Plugin\Init as PluginInit;

class SqlCache
{
    private static $ttl = 60 * 60;
    private static $dbMd5s = [];
    private static $dbConfigs = [];

    /**
     * 列表转化为小写
     * @param $list
     * @return array
     */
    private static function listToLower($list)
    {
        $ret = [];
        foreach ($list as $key => $val) {
            foreach ($val as $k => $v) {
                $ret[$key][strtolower($k)] = $v;
            }
        }
        return $ret;
    }

    /**
     * 错误信息打印
     * @param null $e
     * @param string $name
     * @return array
     */
    private static function errorMessage($e = null, $name = '', $conn = '')
    {
        $sqlErrorPass = 'SQL_ERROR_PASS';
        if (env($sqlErrorPass)) {
            return [];
        }
        $sMsg = "{$name} 查询错误： 可修改.env文件 {$sqlErrorPass}=true 跳过";
        $dirMsg = "错误路径： database.connections.{$conn}";
        $msg = $e->getMessage();
        if (strpos($msg, "could not find driver") !== false) {
            echo("<div>{$name} 扩展未安装</div>");
        }
        if (count($_POST) > 0) {
            EXITJSON(0, $sMsg . "\n" . $dirMsg . "\n" . $msg);
        } else {
            __exit("<div>$sMsg</div><div>$dirMsg</div><div>$msg</div>");
        }
    }
    
    /**
     * 获取MYSQL字段信息
     * @param $conn
     * @param string $table
     * @return array
     */
    private static function getDbMysql($conn, $table = '')
    {
        try {
            $database = config("database.connections.{$conn}.database");
            $db = DB::connection($conn);
            $tableSqls = self::listToLower($db->select("select table_name, table_comment from information_schema.tables where table_schema='{$database}' and table_name='{$table}'"));
            $fieldSqls = self::listToLower($db->select("select table_name, column_name, column_comment, column_type, column_key from information_schema.columns where table_schema='{$database}' and table_name='{$table}'"));
            $dbData = [];
            foreach ($tableSqls as $key => $val) {
                $dbData[$val['table_name']]['name'] = $val['table_comment'];
            }

            foreach ($fieldSqls as $key => $val) {
                $cn = strtolower(trim($val['column_name']));
                $dbData[$val['table_name']]['field'][$cn] = [
                    'name' => $val['column_comment'],
                    'key' => $val['column_key'],
                    'type' => $val['column_type'],
                ];
            }
            $newDbData = [];
            foreach ($dbData as $key => $val) {
                $key = strtolower(trim($key));
                $newDbData[$key] = $val;
            }
            unset($tableSqls);
            unset($fieldSqls);
            unset($dbData);
            return $newDbData;
        } catch (\Exception $e) {
            self::errorMessage($e, 'Mysql', $conn);
        }
    }

    /**
     * 获取MSSQL字段信息， 版本MSSQL2012
     * @param $conn
     * @param string $table
     * @return array
     */
    private static function getDbMssql($conn, $table = '')
    {
        try {
            $db = DB::connection($conn);
            //遍历表
            $tableNames = [];
            $objectIds = [];
            foreach ($db->select("select name, object_id from sys.tables where name='{$table}' order by name asc") as $key => $val) {
                $tableNames[strtolower($val->name)] = $val->object_id;
                $objectIds[] = $val->object_id;
            }

            if (empty($objectIds)) {
                return [];
            }

            $objectIdStr = implode(",", $objectIds);

            //遍历字段类型
            $typeNames = [];
            foreach ($db->select("select user_type_id, name, max_length from sys.types") as $key => $val) {
                $typeNames[$val->user_type_id] = $val->name;
            }

            //遍历注释
            $remarkNames = [];
            foreach ($db->select("select major_id, minor_id, value from sys.extended_properties") as $key => $val) {
                $remarkNames[$val->major_id][$val->minor_id] = $val->value;
            }

            //遍历字段
            $fieldNames = [];
            $sqlStr = "select user_type_id, name, object_id, column_id, COLUMNPROPERTY(object_id, name,'PRECISION') as max_length from sys.columns where object_id in({$objectIdStr})";
            foreach ($db->select($sqlStr) as $key => $val) {
                $fieldNames[$val->object_id][$val->column_id] = [
                    'name' => $val->name,
                    'user_type_id' => $val->user_type_id,
                    'max_length' => $val->max_length,
                    'object_id' => $val->object_id
                ];
            }


            //查询主键
            $pkNames = [];
            $constraintTables = [];
            $constraintNames = [];
            $sqlStr = "select table_name, constraint_name from information_schema.table_constraints where table_name='{$table}' and constraint_type = 'PRIMARY KEY'";
            foreach ($db->select($sqlStr) as $key => $val) {
                $constraintTables[$val->constraint_name] = $val->table_name;
                $constraintNames[] = "'" . $val->constraint_name . "'";
            }

            if (count($constraintNames) > 0) {
                $constraintNameStr = implode(",", $constraintNames);
                $sqlStr = "select constraint_name, column_name from information_schema.constraint_column_usage where constraint_name in({$constraintNameStr})";
                foreach ($db->select($sqlStr) as $key => $val) {
                    $t = strtolower(trim($constraintTables[$val->constraint_name]));
                    $f = strtolower(trim($val->column_name));
                    $pkNames[$t][$f] = true;
                }
            }

            $dbData = [];
            foreach ($tableNames as $key => $val) {
                $dbData[$key]['name'] = $remarkNames[$val][0];
                if (!empty($fieldNames[$val]) && is_array($fieldNames[$val])) {
                    foreach ($fieldNames[$val] as $k => $v) {
                        $cn = strtolower(trim($v['name']));
                        $pkNames[$key][$cn] ? $keyName = "PRI" : $keyName = "";
                        $dbData[$key]['field'][$cn] = [
                            'name' => $remarkNames[$val][$k],
                            'key' => $keyName,
                            'type' => $typeNames[$v['user_type_id']] . "(" . $v['max_length'] . ")",
                        ];
                    }
                }
            }

            $newDbData = [];
            foreach ($dbData as $key => $val) {
                $key = strtolower(trim($key));
                $newDbData[$key] = $val;
            }
            unset($tableNames);
            unset($typeNames);
            unset($remarkNames);
            unset($fieldNames);
            unset($pkNames);
            unset($dbData);
            return $newDbData;
        } catch (\Exception $e) {
            self::errorMessage($e, 'Sqlserver', $conn);
        }
    }

    /**
     * 获取Pgsql字段信息， 版本 PostgreSQL 12.2
     * @param $conn
     * @param string $table
     * @return array
     */
    private static function getDbPgsql($conn, $table = '')
    {
        try {
            $db = DB::connection($conn);
            $tableList = $db->select(<<<EOF
select
relname as table,
cast(obj_description(relfilenode,'pg_class') as varchar) as name
from pg_class c
where
relkind = 'r'
and relname='{$table}'
and relname not like 'pg_%'
and relname not like 'sql_%'
order by relname
EOF
            );
            $newDbData = [];
            if (empty($tableList)) {
                return $newDbData;
            }
            $tList = [];
            foreach ($tableList as $key => $val) {
                $name = $val->name;
                empty($name) && $name = '';
                $table = strtolower($val->table);
                if (strpos($table, "'")) {
                    continue;
                }
                $tList[] = "'{$table}'";
                $newDbData[$table] = [
                    'name' => $name
                ];
            }
            if (empty($newDbData)) {
                return $newDbData;
            }
            $tableStr = implode(",", $tList);
            $pkList = $db->select(<<<EOF
select
pg_attribute.attname as field,
pg_class.relname as table
from
pg_constraint
inner join pg_class
on pg_constraint.conrelid = pg_class.oid
inner join pg_attribute on pg_attribute.attrelid = pg_class.oid
and  pg_attribute.attnum = pg_constraint.conkey[1]
where pg_class.relname in ({$tableStr})
and pg_constraint.contype='p'
EOF
            );
            $pks = [];
            foreach ($pkList as $key => $val) {
                $pks[strtolower($val->table)][strtolower($val->field)] = true;
            }

            $fieldList = $db->select(<<<EOF
select
col_description(a.attrelid, a.attnum) as name,
format_type(a.atttypid, a.atttypmod) as type,
a.attname as field,
c.relname as table
from pg_class as c, pg_attribute as a
where
c.relname in({$tableStr})
AND
a.attrelid = c.oid
AND a.attnum>0
EOF
            );
            foreach ($fieldList as $key => $val) {
                $table = strtolower($val->table);
                if (!isset($newDbData[$table])) {
                    $newDbData[$table] = [];
                }
                if (!isset($newDbData[$table]['field'])) {
                    $newDbData[$table]['field'] = [];
                }
                $k = strtolower($val->field);
                $pKey = '';
                if (isset($pks[$table]) && $pks[$table][$k]) {
                    $pKey = 'PRI';
                }
                $newDbData[$table]['field'][$k] = [
                    'name' => $val->name,
                    'key' => $pKey,
                    'type' => $val->type
                ];
            }
            unset($tableList);
            unset($tList);
            unset($pkList);
            unset($pks);
            unset($fieldList);
            return $newDbData;
        } catch (\Exception $e) {
            self::errorMessage($e, 'PostgreSql', $conn);
        }
    }

    /**
     * 获取Sqlite字段信息， 版本 Sqlite3
     * @param $conn
     * @param string $table
     * @return array
     */
    private static function getDbSqlite($conn, $table = '')
    {
        try {
            $dbFile = self::getDbConfig($conn, 'database');
            $newDbData = [];
            if (!is_file($dbFile)) {
                return $newDbData;
            }
            $db = DB::connection($conn);
            $tableList = $db->select("select name as 'table' from sqlite_master where type='table' and name<>'sqlite_sequence' and name='{$table}' order by name");
            if (empty($tableList)) {
                return $newDbData;
            }
            foreach ($tableList as $key => $val) {
                $table = strtolower($val->table);
                if (strpos($table, "'")) {
                    continue;
                }
                $newDbData[$table] = [
                    'name' => ''
                ];
            }
            if (empty($newDbData)) {
                return $newDbData;
            }
            foreach ($newDbData as $key => $val) {
                $fieldList = $db->select("PRAGMA table_info('{$key}')");
                $fieldInfo = [];
                foreach ($fieldList as $k => $v) {
                    if ($v->pk == '1') {
                        $pKey = 'PRI';
                    } else {
                        $pKey = '';
                    }
                    $fieldInfo[strtolower($v->name)] = [
                        'name' => '',
                        'key' => $pKey,
                        'type' => $v->type
                    ];
                }
                $newDbData[$key]['field'] = $fieldInfo;
            }
            unset($tableList);
            return $newDbData;
        } catch (\Exception $e) {
            self::errorMessage($e, 'Sqlite3', $conn);
        }
    }

    /**
     * 获取数据库配置缓存ID， IP#Port#Database 组合
     * @param $info
     * @return bool|string
     */
    private static function getDbConfigCacheId($info)
    {
        $driver = $info['driver'];
        if (empty($driver)) {
            return '';
        }

        if ($driver == 'sqlite') {
            $md5Str = $info['database'];
        } else {
            $md5s = [];
            $host = $info['host'];
            if (empty($host) || !is_string($host)) {
                return '';
            } else {
                $host = trim($host);
                if (empty($host)) {
                    return '';
                }
            }
            $md5s[] = $host;
            $port = $info['port'];
            if (!empty($port) && (is_string($port) || is_numeric($port))) {
                $port = trim($port . "");
                if (!empty($port)) {
                    $md5s[] = $port;
                }
            }
            $database = $info['database'];
            if (empty($database) || !is_string($database)) {
                return '';
            } else {
                $database = trim($database);
                if (empty($database)) {
                    return '';
                }
            }
            $md5s[] = $database;
            $md5Str = implode("#", $md5s);
        }
        return substr(md5($md5Str), 8, 16);
    }

    /**
     * 获取数据库配置缓存
     * @return array
     */
    private static function getDbMd5($conn = '')
    {
        if (empty($conn)) {
            return '';
        }
        if (isset(self::$dbMd5s[$conn])) {
            return self::$dbMd5s[$conn];
        }

        $dbInfo = self::getDbConfig($conn);
        self::$dbMd5s[$conn] = self::getDbConfigCacheId($dbInfo);
        return self::$dbMd5s[$conn];
    }

    /**
     * 获取database配置
     * @param string $conn
     * @return array|null
     */
    private static function __getDbConfig($conn = '')
    {
        $ret = [];
        if (empty($conn) || !is_string($conn)) {
            return $ret;
        }
        $conn = trim($conn);
        if (empty($conn)) {
            return $ret;
        }

        $config = self::$dbConfigs[$conn];
        if (isset($config)) {
            return $config;
        }

        $config = config("database.connections.{$conn}");
        if (empty($config) || !is_array($config)) {
            return $ret;
        }

        if (!in_array($config['driver'], ['mysql', 'sqlsrv', 'pgsql', 'sqlite'])) {
            return $ret;
        }
        self::$dbConfigs[$conn] = $config;
        return $config;
    }

    /**
     * 获取database配置
     * @param string $conn
     * @param string $keyName
     * @return array|mixed|null|string
     */
    public static function getDbConfig($conn = '', $keyName = '')
    {
        $ret = self::__getDbConfig($conn);
        if (empty($keyName) || !is_string($keyName)) {
            return $ret;
        }
        $keyName = trim($keyName);
        if (empty($keyName)) {
            return $ret;
        }
        $retValue = $ret[$keyName];
        if (!isset($retValue)) {
            $retValue = '';
        }
        return $retValue;
    }

    /**
     * 获取数据库字段命名信息
     * @param $conn
     * @return array
     */
    private static function getDb($conn, $table = '')
    {
        if (empty($table)) {
            return [];
        }

        $type = self::getDbConfig($conn, 'driver');

        try {
            if ($type == 'mysql') {
                $ret = self::getDbMysql($conn, $table);
            } elseif ($type == 'sqlsrv') {
                $ret = self::getDbMssql($conn, $table);
            } elseif ($type == 'pgsql') {
                $ret = self::getDbPgsql($conn, $table);
            } elseif ($type == 'sqlite') {
                $ret = self::getDbSqlite($conn, $table);
            } else {
                return [];
            }
            return $ret;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 获取数据库表和字段信息
     * @param string $conn
     * @param bool $isUpdate
     * @return array|mixed|null
     */
    public static function getTableInfo($conn = '', $table = '', $isUpdate = false)
    {
        if (empty($conn)) {
            $gTpl = \Tphp\Config::$tpl;
            if (!empty($gTpl) && is_array($gTpl->config) && is_array($gTpl->config['config']) && isset($gTpl->config['config']['conn'])) {
                $conn = $gTpl->config['config']['conn'];
            }

            if (empty($conn)) {
                $conn = config('database.default');
            }
        }

        $conn = PluginInit::getConnectionName($conn);

        if (is_function($table)) {
            $table = $table();
        }
        $table = strtolower(trim($table));

        if (empty($conn) || !is_string($conn)) {
            return [];
        }
        $conn = trim($conn);
        if (empty($conn)) {
            return [];
        }

        $prefix = strtolower(trim(SqlCache::getDbConfig($conn, 'prefix')));

        $cacheId = self::getDbMd5($conn);
        if (empty($cacheId)) {
            return [];
        }

        $pTable = "{$prefix}{$table}";

        $cacheId = "db#{$cacheId}#{$pTable}";
        $tableInfo = null;
        if (!$isUpdate) {
            $tableInfo = Cache::get($cacheId);
            if (empty($tableInfo)) {
                $isUpdate = true;
            }
        }
        if ($isUpdate || empty($tableInfo)) {
            $tableInfo = self::getDb($conn, $pTable);
            Cache::put($cacheId, $tableInfo, self::$ttl);
        }

        if (empty($tableInfo[$table])) {
            return [];
        }

        return $tableInfo[$table]['field'];
    }
}
