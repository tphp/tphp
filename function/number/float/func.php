<?php
return function ($num, $bit = 2, $isFormat = true, $isCut = false) {
    if (!is_string($num) && !is_numeric($num)) {
        return 0;
    }

    $num = $num * 1;
    list($int, $float) = explode(".", $num . "");
    $fl = 5;
    if (strlen($float) > $fl && $float[$fl] > 0) {
        if ($float[$fl] > 5) {
            $float = ('0.00000' . (10 - $float[$fl])) * 1;
        } else {
            $float = 0;
        }
        if ($float > 0) {
            $num < 0 ? $num = $num * 1 - $float : $num = $num * 1 + $float;
        }
    }

    if (is_string($isCut)) {
        if (strtolower(trim($isCut)) == 'true') {
            $isCut = true;
        } else {
            $isCut = false;
        }
    } elseif (!is_bool($isCut)) {
        $isCut = false;
    }

    if (!$isCut) {
        $num = round($num, $bit) . "";
    }

    if ($bit > 0) {
        list($int, $float) = explode(".", $num . "");
        empty($int) && $int = '0';
        $fLen = strlen($float);
        if ($fLen > $bit) {
            $float = substr($float, 0, $bit);
        } else {
            $float = str_pad($float, $bit, "0");
        }
        $numStr = $int . "." . $float;
    }

    if ($isFormat === true || strtolower($isFormat) == 'true') {
        if ($numStr[0] == '-') {
            $start = "-";
        } else {
            $start = "";
        }
        list($int, $float) = explode(".", $numStr);
        $int = trim($int, '-');
        $int = number_format($int, "0", ".", ",");
        $numStr = $start . $int . "." . $float;
    }

    return $numStr;
};