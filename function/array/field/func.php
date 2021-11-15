<?php

return function ($arr) {
    $args = func_get_args();
    unset($args[0]);
    if (!isset($args[1]) || empty($args[1])) return $arr;

    $argsKey = strtolower(trim($args[1]));
    unset($args[1]);

    //不区分大小写匹配
    $arrLowers = [];
    foreach ($arr as $key => $val) {
        if (is_array($val)) {
            $tp = [];
            foreach ($val as $k => $v) {
                $tp[strtolower(trim($k))] = $v;
            }
            $arrLowers[] = $tp;
        }
    }

    if (empty($arrLowers)) return null;

    $argsCot = count($args);
    $retArr = [];
    if ($argsCot < 1) {
        foreach ($arrLowers as $key => $val) {
            $retArr[] = $val[$argsKey];
        }
    } elseif ($argsCot < 2) {
        $argsKey2 = strtolower(trim($args[2]));
        foreach ($arrLowers as $key => $val) {
            $retArr[$val[$argsKey]] = $val[$argsKey2];
        }
    } else {
        $newArgs = [];
        foreach ($args as $v) {
            $newArgs[] = strtolower(trim($v));
        }

        foreach ($arrLowers as $key => $val) {
            $tmpArr = [];
            foreach ($newArgs as $v) {
                $tmpArr[] = $val[$v];
            }
            $retArr[$val[$argsKey]] = $tmpArr;
        }
    }

    return $retArr;
};