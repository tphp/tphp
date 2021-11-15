<?php

return function ($data, $dtInfo = "") {
    if (empty($dtInfo) && $dtInfo !== 0 && $dtInfo !== '0') return $data;
    $funLen = func_num_args();
    $args = func_get_args();
    if ($funLen <= 2) return $data;
    if ($funLen <= 3) return apcu($args[2], $data);
    $key = $args[2];
    $newArgs = [];
    for ($i = 3; $i < $funLen; $i++) {
        $newArgs[] = $args[$i];
    }
    return Tphp\Basic\Apcu::__init()->apcuReturn([$key, $newArgs], $data);
};