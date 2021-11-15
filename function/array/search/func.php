<?php

return function ($arr, $str = "", $isCase = false, $strict = null) {
    if ($isCase) return array_search($str, $arr, $strict);

    $newArr = [];
    $str = strtolower($str);
    foreach ($arr as $key => $val) {
        if (!is_array($val)) $newArr[$key] = strtolower($val);
    }

    return array_search($str, $newArr, $strict);
};