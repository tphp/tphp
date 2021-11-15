<?php

/**
 * $isSys 是否是系统array_merge函数
 */
return function ($data, $arrs = null, $isSys = false) {
    if (empty($arrs)) return $data;
    if (empty($data)) return $arrs;

    if ($isSys) return array_merge($data, $arrs);

    $funNameLoop = "func_array_merge_loop";
    if (!function_exists($funNameLoop)) {
        function func_array_merge_loop($data = null, $retKey = [], &$retVal = [])
        {
            foreach ($data as $key => $val) {
                $newKey = $retKey;
                $newKey[] = $key;
                if (!is_array($val)) {
                    if (empty($val) && $val !== 0 && $val !== '') {
                        $retVal[] = [$newKey];
                    } else {
                        $retVal[] = [$newKey, $val];
                    }
                } else {
                    func_array_merge_loop($val, $newKey, $retVal);
                }
            }
            return $retVal;
        }
    }

    $funNameSetArray = "func_array_merge_set_array";
    if (!function_exists($funNameSetArray)) {
        function func_array_merge_set_array(&$arrData, $key = null, $value = null)
        {
            if (is_array($key)) { //如果$key是数组
                $keyStr = "";
                $keyArr = [];
                foreach ($key as $v) {
                    $keyStr .= "['{$v}']";
                    $keyArr[] = $keyStr;
                    eval("if(!is_array(\$arrData{$keyStr})) { unset(\$arrData{$keyStr});}");
                }

                if (empty($value) && $value !== 0 && $value !== '') {
                    eval("unset(\$arrData{$keyStr});");
                    foreach ($keyArr as $v) {
                        $vBool = false;
                        eval("if(empty(\$arrData{$v})) { unset(\$arrData{$v}); \$vBool = true;}");
                        if ($vBool) break;
                    }
                } else {
                    eval("\$arrData{$keyStr} = \$value;");
                }
            } else { //如果$key是字符串
                if (empty($value) && $value !== 0 && $value !== '') {
                    unset($arrData[$key]);
                } else {
                    eval("if(!is_array(\$arrData['{$key}'])) { unset(\$arrData['{$key}']);}");
                    $arrData[$key] = $value;
                }
            }
        }
    }

    $newData = $funNameLoop($arrs);
    foreach ($newData as $val) {
        if (count($val[0]) == 1) {
            $funNameSetArray($data, $val[0][0], $val[1]);
        } else {
            $funNameSetArray($data, $val[0], $val[1]);
        }
    }

    return $data;
};