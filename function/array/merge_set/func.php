<?php

/**
 * 数组设置
 * 当key为空时返回空
 * 当value为空时销毁对应端key
 */
return function ($data, $key = null, $value = null) {
    if (empty($key)) return null;

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

    $funNameSetArray($data, $key, $value);

    return $data;
};