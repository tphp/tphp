<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl;
use function PHPUnit\Framework\isNull;

/**
 * 数据设置
 * Class Method
 * @package Tphp\Basic\Tpl
 */
class Data
{

    private $page = [
        'ispage' => false,
        'pagesizedef' => 20,
        'page' => 1,
        'pagesize' => 20
    ];

    private $data;
    private $tpl;

    private $isPage = false;
    private $status = false;
    private $value = null;
    private $ini = null;
    private $sql = '';
    private $field = [];
    private $pageInfo = [];

    private $isSet = false;
    
    /**
     * 初始化
     * Data constructor.
     * @param null $data
     * @param null $tpl
     */
    function __construct($data=null, $tpl=null)
    {
        $this->data = $data;
        $this->tpl = $tpl;
    }

    /**
     * 分页设置
     * @param null $page
     * @param null $pageSize
     * @return $this
     */
    public function setPage($page=null, $pageSize=null)
    {
        $this->page['ispage'] = true;
        $this->isPage = true;
        if (!empty($page) && is_integer($page) && $page > 0) {
            $this->page['page'] = $page;
        }
        if (!empty($pageSize) && is_integer($pageSize) && $pageSize > 0) {
            $this->page['pagesize'] = $pageSize;
        }
        return $this;
    }

    /**
     * 配置文件设置
     * @param null $ini
     * @return $this
     */
    public function setIni($ini=null)
    {
        if (empty($ini) || !is_array($ini)) {
            return $this;
        }

        $ini = $this->tpl->keyToLower($ini);
        
        if (!empty($ini) && is_array($ini)) {
            $this->ini = $ini;
        }
        
        return $this;
    }

    /**
     * 反向设置到模板中
     */
    public function reset()
    {
        $this->tpl->resetDataIni($this->data, $this->ini, $this->page);
        return $this;
    }

    /**
     * 数据初始化
     */
    private function __setInit()
    {
        if ($this->isSet) {
            return;
        }

        $this->isSet = true;
        
        $data = $this->data;
        isset($data['type']) && $dataType = $data['type']; //数据类型

        if (empty($data['config'])) { //数据配置项
            $retData = $data['default'];
            if (!is_null($retData)) {
                $this->status = true;
                $this->value = $retData;
            }
            return;
        }
        
        list($isDefault, list($status, $retData, $field, $pageInfo, $sql)) = $this->tpl->getDataInfo($dataType, $data['config'], $data['default'], $this->page);

        $ini = $this->ini;
        if (!empty($ini)) {
            if (is_array($ini['#sql'])) {
                if (!empty($ini['#sql'])) {
                    list($srcData, $retData) = $this->tpl->getDataToIni($retData, $dataType, $ini);
                }
            } else {
                $retData = $this->tpl->getDataToIni($retData, $dataType, $ini);
            }
        }
        
        $this->value = $retData;
        if (!$status) {
            return;
        }

        $this->status = true;
        $this->field = $field;
        $this->pageInfo = $this->tpl->getPageInfo($pageInfo);
        $this->sql = $sql;
    }

    /**
     * 获取状态
     * @return string
     */
    public function status()
    {
        $this->__setInit();
        return $this->status;
    }
    
    /**
     * 获取数据库语句
     * @return string
     */
    public function sql()
    {
        $this->__setInit();
        return $this->sql;
    }

    /**
     * 获取数据库字段信息
     * @return string
     */
    public function field()
    {
        $this->__setInit();
        return $this->field;
    }

    /**
     * 获取分页信息
     * @return string
     */
    public function isPage()
    {
        return $this->isPage;
    }

    /**
     * 获取分页信息
     * @return string
     */
    public function page()
    {
        $this->__setInit();
        return $this->pageInfo;
    }
    
    /**
     * 获取值
     * @return string
     */
    public function value()
    {
        $this->__setInit();
        return $this->value;
    }

    /**
     * 获取值
     * @return string
     */
    public function json()
    {
        $this->__setInit();
        if (!$this->isPage) {
            return $this->value;
        }

        return [
            'page' => $this->pageInfo,
            'list' => $this->value
        ];
    }
}
