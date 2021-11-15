<?php

return function ($data, $setData = "", $if = "", $value = "", $trueSet = "", $falseSet = "") {
    $funLen = func_num_args();
    if (empty($if) || $funLen <= 4) return $data;
    if (empty($setData)) $setData = $data;
    $if = trim($if);
    $bool = false;
    $val = "";
    if (is_string($value) || is_numeric($value) || is_bool($value)) {
        $val = $value;
    } elseif (is_array($value) && !empty($value)) {
        if (count($value) == 1) {
            $val = $value[0];
        } else {
            $val = $value;
        }
    }

    if (is_string($val) || is_numeric($val) || is_bool($val)) {
        switch ($if) {
            case '>' :
                $bool = ($setData > $val);
                break;
            case '>=' :
                $bool = ($setData >= $val);
                break;
            case '=' :
            case '==' :
                $bool = ($setData == $val);
                break;
            case '!' :
            case '!=' :
                $bool = ($setData != $val);
                break;
            case '<' :
                $bool = ($setData < $val);
                break;
            case '<=' :
                $bool = ($setData <= $val);
                break;
            case '&&' :
                $bool = ($setData && $val);
                break;
            case '||' :
                $bool = ($setData || $val);
                break;
        }
    } elseif (is_array($val)) {
        switch ($if) {
            case '>' :
                $bool = ($setData > max($val));
                break;
            case '>=' :
                $bool = ($setData >= max($val));
                break;
            case '=' :
            case '==' :
            case '||' :
                $bool = in_array($setData, $val);
                break;
            case '!' :
            case '!=' :
                $bool = !in_array($setData, $val);
                break;
            case '<' :
                $bool = ($setData < min($val));
                break;
            case '<=' :
                $bool = ($setData <= min($val));
                break;
            case 'in' : //范围之内
                if (count($val) >= 2) {
                    $bool = ($setData >= $val[0] && $setData <= $val[1]);
                }
                break;
            case 'out' : //范围之外
                if (count($val) >= 2) {
                    $bool = ($setData < $val[0] && $setData > $val[1]);
                }
                break;
        }
    }

    if ($bool) {
        return $trueSet;
    } else if ($funLen >= 6) {
        return $falseSet;
    }

    return $data;
};