<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Plugin;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tphp\Basic\Sql\Init as SqlInit;
use Tphp\Basic\Sql\SqlCache;
use Tphp\Basic\Sql\EmptyQueryBuilder;

/**
 * Eloquent ORM
 */
class PluginModel extends Model
{
    /**
     * 缓存配置，将所有配置传递到其他新建的Model类
     *
     * @var array
     */
    protected $config;

    /**
     * 判断插件是否正常加载，只有正常加载才能进行下一步操作
     *
     * @var bool
     */
    public $status = false;

    /**
     * 表名
     *
     * @var
     */
    public $table;

    /**
     * 链接名
     *
     * @var
     */
    public $conn;

    /**
     * 系统创建时间默认为create_time
     * env('CREATED_AT') 优先
     *
     * @var string
     */
    public $createdAt = 'create_time';

    /**
     * 创建时间备注
     * env('CREATED_AT_COMMENT') 优先
     *
     * @var string
     */
    public $createdAtComment = '创建时间';

    /**
     * 系统更新时间默认为update_time
     * env('UPDATED_AT') 优先
     *
     * @var string
     */
    public $updatedAt = 'update_time';

    /**
     * 更新时间备注
     * env('UPDATED_AT_COMMENT') 优先
     *
     * @var string
     */
    public $updatedAtComment = '更新时间';

    /**
     * 是否创建数据库
     *
     * @var bool
     */
    public $isInitCreate = false;

    /**
     * 日期格式化，转化为时间戳设置值： U
     * env('DATE_FORMAT') 优先
     *
     * @var string
     */
    public $dateFormat = 'Y-m-d H:i:s';

    /**
     * 判断是否是空模型
     * @var bool
     */
    private $isEmptyModel = false;

    /**
     * @var array
     */
    private $blueprint = [];

    /**
     * PluginModel constructor.
     * @param array $attributes 属性设置
     * @param array $config 数据库链接， 如果为空则默认链接，如果为字符串则获取database.php文件获取，如果数组则新增链接
     * @param null $pluObj 插件对象
     * @param array $reset 数组重设
     */
    public function __construct(array $attributes = [], $config = [], $pluObj = null, $reset = [])
    {
        $this->pluObj = $pluObj;
        if (!empty($reset)) {
            foreach ($reset as $rKey => $rVal) {
                if (is_null($rVal)) {
                    unset($config[$rKey]);
                } else {
                    $config[$rKey] = $rVal;
                }
            }
        }
        $this->setModelInit($config);
        $this->config = $config;
        parent::__construct($attributes);
    }

    /**
     * 设置空模型
     * @param bool $bool
     */
    public function setEmptyModel($bool = true)
    {
        $this->isEmptyModel = $bool;
    }

    /**
     * To: parent::newInstance()
     * 重写父类方法，支持自定义参数配置
     * Create a new instance of the given model.
     *
     * @param  array $attributes
     * @param  bool $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = new static((array)$attributes, $this->config);

        $model->exists = $exists;

        $model->setConnection(
            $this->getConnectionName()
        );

        $model->setTable($this->getTable());

        $model->mergeCasts($this->casts);

        return $model;
    }

    /**
     * 日期格式重写
     *
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format($this->getDateFormat());
    }

    /**
     * 修复自定义CREATED_AT或UPDATED_AT错误
     *
     * @param  int $precision
     * @return void
     */
    public function timestampsToInteger()
    {
        if ($this->dateFormat === 'U') {

            $this->Integer($this->getCreatedAtColumn())->nullable();

            $this->Integer($this->getUpdatedAtColumn())->nullable();

            return true;
        }

        return false;
    }

    /**
     * 修复自定义CREATED_AT或UPDATED_AT错误
     *
     * @param  int $precision
     * @return void
     */
    public function timestamps($precision = 0)
    {
        if ($this->timestampsToInteger()) {
            return;
        }

        $this->timestamp($this->getCreatedAtColumn(), $precision)->nullable();

        $this->timestamp($this->getUpdatedAtColumn(), $precision)->nullable();
    }

    /**
     * Add creation and update timestampTz columns to the table.
     *
     * @param  int $precision
     * @return void
     */
    public function timestampsTz($precision = 0)
    {
        if ($this->timestampsToInteger()) {
            return;
        }

        $this->timestampTz($this->getCreatedAtColumn(), $precision)->nullable();

        $this->timestampTz($this->getUpdatedAtColumn(), $precision)->nullable();
    }

    /**
     * Indicate that the timestamp columns should be dropped.
     *
     * @return void
     */
    public function dropTimestamps()
    {
        $this->dropColumn($this->getCreatedAtColumn(), $this->getUpdatedAtColumn());
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function getCreatedAtColumn()
    {
        if (is_string($this->createdAt) && !empty($this->createdAt)) {
            $createdAt = trim($this->createdAt);
            if (!empty($createdAt)) {
                return $createdAt;
            }
        }
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        if (is_string($this->updatedAt) && !empty($this->updatedAt)) {
            $updatedAt = trim($this->updatedAt);
            if (!empty($updatedAt)) {
                return $updatedAt;
            }
        }
        return static::UPDATED_AT;
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string $column
     * @return $this
     */
    public function latest($column = null)
    {
        empty($column) && $column = $this->getCreatedAtColumn();
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string $column
     * @return $this
     */
    public function oldest($column = null)
    {
        empty($column) && $column = $this->getUpdatedAtColumn();
        return $this->orderBy($column, 'asc');
    }

    private function getThisInfos()
    {
        $keyName = 'ModelDefaultInfo';
        $info = \Tphp\Config::$plugins['class'][$keyName];
        if (!empty($info)) {
            return $info;
        }
        $parentRef = new \ReflectionClass($this);
        $parentKeys = array_keys($parentRef->getDefaultProperties());
        $retInfo = [];
        foreach ($parentKeys as $key) {
            $pInfo = $parentRef->getProperty($key);
            if (!$pInfo->isPrivate()) {
                if ($pInfo->isStatic()) {
                    $retInfo[$key] = true;
                } else {
                    $retInfo[$key] = false;
                }
            }
        }
        \Tphp\Config::$plugins['class'][$keyName] = $retInfo;
        return $retInfo;
    }

    /**
     * 设置备注，针对SQLserver的备注补充，Mysql、PostgreSql已默认实现，sqlite不支持
     *
     * @param array $comments
     * @param string $table
     * @param string $conn
     */
    private function setModelInitComment($comments = [], $table = '', $conn = '', $driver = '')
    {
        if (strpos($table, '"') !== false || strpos($table, "'") !== false) {
            return;
        }
        $sqlCommads = [];

        if ($driver == 'sqlsrv') {
            foreach ($comments as $fieldName => $comment) {
                if (strpos($fieldName, '"') !== false || strpos($fieldName, "'") !== false) {
                    continue;
                }
                $comment = str_replace("'", "''", trim($comment));
                if (!empty($comment)) {
                    $sqlCommads[] = "execute sp_addextendedproperty 'MS_Description','{$comment}','user','dbo','table','{$table}','column','{$fieldName}';";
                }
            }
        } else {
            return;
        }

        if (empty($sqlCommads)) {
            return;
        }

        $sqlStr = implode("\n", $sqlCommads);
        if (!empty($sqlStr)) {
            SqlInit::runDbExcute(\DB::connection($conn), $sqlStr);
        }
    }

    private function setModelInit(&$config = [])
    {
        if (empty($config)) {
            return;
        }
        $field = [];
        $before = $config['before'];
        if (isset($config['field'])) {
            $field = $config['field'];
            // 构建表结构一次就够了
            unset($config['field']);
        }

        if (isset($before)) {
            unset($config['before']);
        }

        $parentInfos = $this->getThisInfos();
        foreach ($config as $key => $val) {
            if (!isset($parentInfos[$key])) {
                continue;
            }
            if ($parentInfos[$key]) {
                self::$$key = $val;
            } else {
                $this->$key = $val;
            }
        }
        $table = $this->table;
        $connection = $this->connection;
        if (empty($table) || empty($connection)) {
            return;
        }
        $connInfo = config("database.connections.{$connection}");
        $database = $connInfo['database'];
        if ($connInfo['driver'] == 'sqlite') {
            // 如果是 sqlite， 文件路径不存在则创建一个空文件，避免链接错误。
            if (!empty($database) && !is_file($database)) {
                import('XFile')->write($database, "");
            }
        }
        if ($connInfo['driver'] == 'sqlsrv') {
            $isSetComment = true;
        } else {
            $isSetComment = false;
        }
        if (!empty($field) && is_function($field)) {
            $schema = Schema::connection($connection);
            $timestamps = $this->timestamps;
            $createdAt = $this->getCreatedAtColumn();
            $updatedAt = $this->getUpdatedAtColumn();
            $isUpdateColumns = false;

            if ($this->dateFormat == 'U') {
                $timeInvoke = 'integer';
            } else {
                $timeInvoke = 'timestamp';
            }

            $comments = [];
            $hasTable = $schema->hasTable($table);
            if (is_function($before)) {
                if ($before($hasTable, $this->pluObj) !== true) {
                    $this->status = true;
                    $config['status'] = true;
                    $this->conn = $connection;
                    $config['conn'] = $connection;
                    $tbInfo = SqlCache::getTableInfo($connection, $table);
                    $isUpdate = false;
                    if ($hasTable) {
                        if (empty($tbInfo)) {
                            $isUpdate = true;
                        }
                    } elseif (!empty($tbInfo)) {
                        $isUpdate = true;
                    }
                    if ($isUpdate) {
                        SqlCache::getTableInfo($connection, $table, true);
                    }
                    return;
                }
            }

            $blueprints = [];
            if ($hasTable) {
                // 如果支持$createdAt和$updatedAt则需要检查对应字段是否存在， 不存在则创建
                $cols = $schema->getColumnListing($table);
                $colsKvs = [];
                foreach ($cols as $col) {
                    $colsKvs[strtolower($col)] = true;
                }

                $updateDatas = [];
                // 设置自动创建时间和更新时间，默认已设置
                if ($timestamps) {
                    $addTimes = [];
                    $createdAtLower = strtolower($createdAt);
                    $updatedAtLower = strtolower($updatedAt);
                    if (!$colsKvs[$createdAtLower]) {
                        $colsKvs[$createdAtLower] = true;
                        $addTimes['created_at'] = $createdAt;
                    }
                    if (!$colsKvs[$updatedAtLower]) {
                        $colsKvs[$updatedAtLower] = true;
                        $addTimes['updated_at'] = $updatedAt;
                    }
                    if (!empty($addTimes)) {
                        $updateDatas['times'] = $addTimes;
                    }
                }

                // 判断字段差异
                $blueprint = new Blueprint($table);
                $field($blueprint, $this->pluObj);
                $bCols = $blueprint->getColumns();
                $bColumns = [];
                foreach ($bCols as $col) {
                    $attributes = $col->getAttributes();
                    $attributesName = strtolower($attributes['name']);
                    if (!$colsKvs[$attributesName]) {
                        $bColumns[] = $col;
                    }
                }
                if (!empty($bColumns)) {
                    $updateDatas['columns'] = $bColumns;
                }
                if (!empty($updateDatas)) {
                    $schema->table($table, function (Blueprint $blueprint) use ($updateDatas, $timeInvoke, &$comments, $isSetComment) {
                        $addTimes = $updateDatas['times'];
                        if (!empty($addTimes)) {
                            foreach ($addTimes as $addKey => $addTime) {
                                $added = $blueprint->{$timeInvoke}($addTime)->nullable();
                                if ($addKey == 'created_at') {
                                    if (!empty($this->createdAtComment)) {
                                        $added->comment($this->createdAtComment);
                                    }
                                    $isSetComment && $comments[$addKey] = $this->createdAtComment;
                                } elseif ($addKey == 'updated_at') {
                                    if (!empty($this->updatedAtComment)) {
                                        $added->comment($this->updatedAtComment);
                                    }
                                    $isSetComment && $comments[$addKey] = $this->updatedAtComment;
                                }
                            }
                        }
                        $bColumns = $updateDatas['columns'];
                        if (!empty($bColumns)) {
                            foreach ($bColumns as $bColumn) {
                                $bColumnArray = $bColumn->getAttributes();
                                $bType = $bColumnArray['type'];
                                unset($bColumnArray['type']);
                                $bName = $bColumnArray['name'];
                                unset($bColumnArray['name']);
                                $blueprint->addColumn($bType, $bName, $bColumnArray)->nullable();
                                $isSetComment && $comments[$bName] = $bColumnArray['comment'];
                            }
                        }
                    });
                    $isUpdateColumns = true;
                }
                $blueprints[] = $blueprint;
            } else {
                $schema->create($table, function (Blueprint $blueprint) use ($field, $timestamps, $createdAt, $updatedAt, $timeInvoke, &$comments, $isSetComment, &$blueprints) {
                    $field($blueprint, $this->pluObj);
                    if ($timestamps) {
                        $cols = $blueprint->getColumns();
                        $colsKvs = [];
                        foreach ($cols as $col) {
                            $colAttr = $col->getAttributes();
                            $colsKvs[strtolower($colAttr['name'])] = true;
                            $isSetComment && $comments[$colAttr['name']] = $colAttr['comment'];
                        }
                        // 生成$createdAt和$updatedAt，支持自定义
                        if (!$colsKvs[strtolower($createdAt)]) {
                            $created = $blueprint->{$timeInvoke}($createdAt)->nullable();
                            if (!empty($this->createdAtComment)) {
                                $created->comment($this->createdAtComment);
                            }
                            $isSetComment && $comments[$createdAt] = $this->createdAtComment;
                        }
                        if (!$colsKvs[strtolower($updatedAt)]) {
                            $updated = $blueprint->{$timeInvoke}($updatedAt)->nullable();
                            if (!empty($this->updatedAtComment)) {
                                $updated->comment($this->updatedAtComment);
                            }
                            $isSetComment && $comments[$updatedAt] = $this->updatedAtComment;
                        }
                    }
                    $blueprints[] = $blueprint;
                });
                $isUpdateColumns = true;
                $this->isInitCreate = true;
            }

            if (!empty($blueprints) && !empty($blueprints[0])) {
                $this->blueprint = $blueprints[0];
            }
            
            $this->status = true;
            $config['status'] = true;
            $this->conn = $connection;
            $config['conn'] = $connection;

            if (!empty($comments) && $isSetComment) {
                $this->setModelInitComment($comments, $table, $connection, $connInfo['driver']);
            }

            // 如果有字段更新，则更新字段信息存储缓存
            if ($isUpdateColumns) {
                SqlCache::getTableInfo($connection, $table, true);
            }
        }
    }

    /**
     * 获取字段属性
     * @return array
     */
    public function getFieldNames()
    {
        $names = [];
        $blueprint = $this->blueprint;
        if (empty($blueprint)) {
            return $names;
        }
        
        $bCols = $blueprint->getColumns();
        $fieldNames = [];
        foreach ($bCols as $col) {
            $attributes = $col->getAttributes();
            $attributesName = strtolower($attributes['name']);
            unset($attributes['name']);
            $fieldNames[$attributesName] = $attributes;
        }
        return $fieldNames;
    }

    /**
     * 获取DB构造类，实现更灵活的增删改查
     * @return mixed
     */
    public function db()
    {
        $builder = \DB::connection($this->getConnectionName())->table($this->getTable());
        
        if ($this->isEmptyModel) {
            return new EmptyQueryBuilder($builder);
        }
        return $builder;
    }

    /**
     * 获取数据库字段信息
     * @return array
     */
    public function tableInfo()
    {
        return SqlCache::getTableInfo($this->getConnectionName(), $this->getTable());
    }
}