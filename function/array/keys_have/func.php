<?php

/**
 * 保留键值，已 "," 隔开
 */
return function ($data, $key = "") {
    if (!is_array($data)) {
        return "";
    }
    $keys = explode(",", $key);
    $keyBs = [];
    foreach ($keys as $k) {
        $keyBs[$k] = true;
    }
    $ret = [];
    foreach ($data as $k => $v) {
        $keyBs[$k] && $ret[$k] = $v;
    }
    return $ret;
};