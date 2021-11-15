<?php

/**
 * $isSys 是否是系统array_unique函数
 */
return function ($data, $_this = "", $_next = "", $topValue = "") {
    if (empty($data)) return [];

    if (empty($_this) || empty($_next)) return $data;

    $funName = "func_array_tier";
    if (!function_exists($funName)) {
        /**
         *data:数据组
         *_this:子节点
         *_next:父节点
         *topvalue:顶部值
         */
        function func_array_tier($data, $_this, $_next, $topValue, $data2 = array(), $tmpArr = array())
        {
            if (!isset($topValue) || trim($topValue) == '') {
                if (count($data) > 0) {
                    $topValue = $data[0][$_next];
                    foreach ($data as $key => $val) {
                        if ($val[$_next] < $topValue) {
                            $topValue = $val[$_next];
                        }
                    }
                }
            }

            if (empty($data2)) {
                $parents = array($topValue);
                $tmpArr = &$data2;
                foreach ($data as $key => $val) {
                    if (in_array($val[$_next], $parents)) {
                        unset($val[$_this]);
                        unset($val[$_next]);
                        $tmpArr[$key] = $val;
                        unset($data[$key]);
                    }
                }
            }

            $newTmpArr = array();
            $parents = array();
            foreach ($tmpArr as $key => $val) {
                $parents[] = $key;
                foreach ($data as $k => $v) {
                    if ($key == $v[$_next]) {
                        unset($v[$_this]);
                        unset($v[$_next]);
                        $tmpArr[$key]['_next_'][$k] = $v;
                        $newTmpArr[$k] = &$tmpArr[$key]['_next_'][$k];
                        unset($data[$k]);
                    }
                }
            }
            if (empty($parents) || empty($data) || empty($newTmpArr)) {
                return $data2;
            } else {
                return func_array_tier($data, $_this, $_next, $parents, $data2, $newTmpArr);
            }
        }
    }

    $newData = [];
    foreach ($data as $key => $val) {
        $newData[$val[$_this]] = $val;
    }
    return $funName($newData, $_this, $_next, $topValue);
};