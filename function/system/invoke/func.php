<?php

return function ($data, $invokeName = "") {
    if (empty($invokeName)) return $data;
    if (!function_exists($invokeName)) return null;
    $args = func_get_args();
    unset($args[0]);
    unset($args[1]);
    if (empty($args)) return $invokeName($data);
    $argStr = "";
    foreach ($args as $key => $val) {
        $argStr .= ", \$args['{$key}']";
    }
    $ret = "";
    eval("\$ret = {$invokeName}(\$data{$argStr});");
    return $ret;
};