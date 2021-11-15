<?php
/**
 * $str : 字符串
 * $isMb ： 是否中文类型
 */
return function ($str, $isMb = false) {
    if ($isMb) {
        return mb_strlen($str);
    } else {
        return strlen($str);
    }
};