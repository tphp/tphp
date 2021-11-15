<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Sql;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Eloquent ORM
 */
class EmptyQueryBuilder extends QueryBuilder
{
    function __construct(QueryBuilder $queryBuilder)
    {
        parent::__construct($queryBuilder->getConnection(), $queryBuilder->getGrammar(), $queryBuilder->getProcessor());
    }
    
    /**
     * 获取数据列表
     * @param array $columns
     * @return array|\Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        return [];
    }

    /**
     * 获取单个数据
     * @param int|string $id
     * @param array $columns
     * @return array|QueryBuilder|mixed
     */
    public function find($id, $columns = ['*'])
    {
        return [];
    }

    /**
     * @param string $column
     * @return array|mixed
     */
    public function value($column)
    {
        return [];
    }

    /**
     * @param string $columns
     * @return int
     */
    public function count($columns = '*')
    {
        return 0;
    }

    /**
     * @param array $values
     * @return bool|int
     */
    public function insert(array $values)
    {
        return 0;
    }

    /**
     * @param array $values
     * @return int
     */
    public function insertOrIgnore(array $values)
    {
        return 0;
    }

    /**
     * @param array $values
     * @param null $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        return 0;
    }

    /**
     * @param array $columns
     * @param \Closure|QueryBuilder|string $query
     * @return int
     */
    public function insertUsing(array $columns, $query)
    {
        return 0;
    }

    /**
     * @param array $values
     * @return int
     */
    public function update(array $values)
    {
        return 0;
    }

    /**
     * @param array $attributes
     * @param array $values
     * @return bool|int
     */
    public function updateOrInsert(array $attributes, array $values = [])
    {
        return 0;
    }

    /**
     * @param array $values
     * @param array|string $uniqueBy
     * @param null $update
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        return 0;
    }

    /**
     * @param string $column
     * @param int $amount
     * @param array $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        return 0;
    }

    /**
     * @param string $column
     * @param int $amount
     * @param array $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        return 0;
    }

    /**
     * @param null $id
     * @return int
     */
    public function delete($id = null)
    {
        return 0;
    }

    /**
     * @return int|void
     */
    public function truncate()
    {
        return 0;
    }
}
