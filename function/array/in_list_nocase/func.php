<?php

return function ($arr) {
    if (empty($arr)) return [];
    $newArr = [];
    foreach ($arr as $key => $val) {
        !is_array($val) && $newArr[$key] = $val;
    }
    if (empty($newArr)) return [];

    $args = func_get_args();
    unset($args[0]);
    if (!isset($args[1]) || empty($args[1])) return $arr;


    $ret = [];
    foreach ($args as $key => $val) $args[$key] = strtolower($val);
    foreach ($arr as $key => $val) {
        $val = strtolower($val);
        in_array($val, $args) && $ret[$key] = $val;
    }
    return $ret;
};