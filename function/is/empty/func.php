<?php

return function ($arr) {
    $args = func_get_args();
    $argsLen = count($args);
    $isempty = empty($arr);
    if ($argsLen <= 1) {
        return "";
    } else if ($isempty) {
        return $args[1];
    } else if ($argsLen >= 3) {
        return $args[2];
    }
    return $arr;
};