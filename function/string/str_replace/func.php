<?php
/**
 * $str : 字符串
 * $search : 搜索字符串
 * $replace ： 替换字符串
 * $isRegular ： 是否使用正则替换
 */
return function ($str, $search = "", $replace = "", $isRegular = false) {
    if ($search == "") return $str;
    if ($isRegular) {
        return preg_replace($search, $replace, $str);
    } else {
        return str_replace($search, $replace, $str);
    }
};