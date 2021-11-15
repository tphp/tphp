<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

use Tphp\Basic\Tpl\Run as Run;

return new class {

    /**
     * 获取浏览器信息
     * @return array
     */
    public function getBrowser()
    {
        return Run::getBrowser();
    }

    /**
     * 判断是否是IE
     * @return bool
     */
    public function isIe()
    {
        list($name) = $this->getBrowser();
        return $name == 'IE';
    }

    /**
     * 繁简体转换
     * @param bool $isTw： true 简体转繁体 false 繁体转简体
     */
    public function trad($isTw = true)
    {
        set_ob_start(function ($html) use($isTw) {
            return apcu([['trad', !$isTw]], $html);
        }, 'trad', true);
    }
};