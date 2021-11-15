<?php

/**
 * $keyName 键名
 */
return function ($data, $keyName = "") {
    if (empty($data)) return [];
    $keyName = trim(strtolower($keyName));
    if (empty($keyName)) return [];

    $newData = [];
    foreach ($data as $key => $val) {
        $kName = trim(strtolower($val[$keyName]));
        unset($val[$keyName]);
        $newData[$kName][] = $val;
    }
    return $newData;
};