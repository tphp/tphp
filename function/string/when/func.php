<?php
/**
 * $str : 字符串
 * $search : 搜索字符串
 * $replace ： 替换字符串
 * $isRegular ： 是否使用正则替换
 */
return function ($str, $search = "", $replace = "", $elseStr = "") {
    $fLen = func_num_args();
    if ($fLen <= 2) return $str;
    if ($str == $search) {
        return $replace;
    } elseif ($fLen >= 4) {
        return $elseStr;
    }
    return $str;
};