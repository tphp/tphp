<?php

return function ($arr, $fieldName = "") {

    if (empty($fieldName)) return $arr;

    $fieldNums = [];
    $fieldStrs = [];
    $fieldArrs = [];
    $fieldNulls = [];
    foreach ($arr as $key => $val) {
        if (isset($val[$fieldName])) {
            $tmp = $val[$fieldName];
            if (is_string($tmp)) {
                $fieldStrs[$key] = $tmp;
            } elseif (is_numeric($tmp)) {
                $fieldNums[$key] = $tmp;
            } else {
                $fieldArrs[$key] = $tmp;
            }
        } else {
            $fieldNulls[$key] = $val;
        }
    }

    asort($fieldNums);
    asort($fieldStrs);

    $ret = [];
    foreach ($fieldNums as $key => $val) $ret[$key] = $arr[$key];
    foreach ($fieldStrs as $key => $val) $ret[$key] = $arr[$key];
    foreach ($fieldArrs as $key => $val) $ret[$key] = $arr[$key];
    foreach ($fieldNulls as $key => $val) $ret[$key] = $arr[$key];
    return $ret;
};