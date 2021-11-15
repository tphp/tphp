<?php

/**
 * $isSys 是否是系统array_unique函数
 */
return function ($data, $isSys = false) {
    if (empty($data)) return [];

    if ($isSys) return array_unique($data);

    $funName = "func_array_unique";
    if (!function_exists($funName)) {
        function func_array_unique($data = null)
        {
            $vars = array_unique($data);
            foreach ($vars as $key => $val) {
                if (is_array($val)) {
                    $vars[$key] = func_array_unique($val);
                } else {
                    $vars[$key] = $val;
                }
            }
            return $vars;
        }
    }

    return $funName($data);
};