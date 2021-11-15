<?php
/**
 * $str : 查找字符串是否存在
 * $findStr : 要查询的字符串
 * $trueSet : 存在并$trueSet不为空时设置
 * $falseSet : 不存在并$falseSet不为空时设置
 */
return function ($str, $findStr = "", $trueSet = "", $falseSet = "") {
    if (empty($str) && empty($findStr)) return "";
    $bool = false;
    strpos($str, $findStr) !== false && $bool = true;
    if (empty($trueSet)) return $str;
    if ($bool) return $trueSet;
    if (empty($falseSet)) return $str;
    return $falseSet;
};