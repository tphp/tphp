<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic\Tpl\Init;

/**
 * 判断类
 * Trait Compare
 * @package Tphp\Basic\Tpl\Init
 */

trait Compare
{
    /**
     * 判断是否是POST提交
     * @return bool
     */
    public function isPost()
    {
        return count($_POST) > 0;
    }
}