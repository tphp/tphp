<?php

return function ($arr, $isAll = false) {
    $funName = "func_array_sort_key_asc";
    if (!function_exists($funName)) {
        function func_array_sort_key_asc($arr, $isAll)
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

                ksort($nums);
                ksort($strs);

                foreach ($strs as $key => $val) {
                    $nums[$key] = $val;
                }

                if ($isAll) {
                    foreach ($nums as $key => $val) {
                        if (is_array($val)) {
                            $nums[$key] = func_array_sort_key_asc($val, $isAll);
                        }
                    }
                }
                return $nums;
            } else {
                return $arr;
            }
        }
    }

    return $funName($arr, $isAll);
};