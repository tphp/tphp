<?php

return function ($arr, $isAll = false) {
    $funName = "func_array_sort_key_desc";
    if (!function_exists($funName)) {
        function func_array_sort_key_desc($arr, $isAll)
        {
            if (is_array($arr)) {
                $nums = [];
                $strs = [];
                foreach ($arr as $key => $val) {
                    if (is_string($key)) {
                        $strs[$key] = $val;
                    } else {
                        $nums[$key] = $val;
                    }
                }

                krsort($nums);
                krsort($strs);

                foreach ($nums as $key => $val) {
                    $strs[$key] = $val;
                }

                if ($isAll) {
                    foreach ($strs as $key => $val) {
                        if (is_array($val)) {
                            $strs[$key] = func_array_sort_key_desc($val, $isAll);
                        }
                    }
                }
                return $strs;
            } else {
                return $arr;
            }
        }
    }

    return $funName($arr, $isAll);
};