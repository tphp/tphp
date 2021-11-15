<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

class MarkDown
{
    public function __construct()
    {
        $this->parseDown = import('ParseDown');
    }

    /**
     * 获取HTML页面
     * 
     * @param string $text
     * @return mixed
     */
    public function getHtml($text = '')
    {
        return $this->parseDown->text($text);
    }

    /**
     * 获取HTML中的纯文本信息
     * 
     * @param string $text
     * @return mixed
     */
    public function getText($text = '')
    {
        $html = $this->getHtml($text);
        $html = htmlspecialchars_decode(preg_replace("/<(.*?)>/si","", $html));
        return $html;
    }

    /**
     * 搜索字符串
     * 
     * @param string $text
     * @param string $search
     * @return bool
     */
    public function isSearch($text = '', $search = '')
    {
        $text = strtolower(trim($text));
        $search = trim($search);
        if (empty($text) || empty($search)) {
            return false;
        }
        $text = $this->getText($text);
        return strpos($text, $search) !== false;
    }
}