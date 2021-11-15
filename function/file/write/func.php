<?php
return function ($fileUrl, $str = "", $addBool = false) {
    if (empty($fileUrl)) return $fileUrl;

    $funName = "func_file_write";
    if (!function_exists($funName)) {
        function func_file_write($path)
        {
            if (is_readable($path)) return;
            $pLen = strlen($path);
            if ($pLen <= 0) return;
            $ti = 0;
            if ($path[0] == '/') {
                $ti = 1;
            } else {
                if ($pLen > 2 && $path[1] == ':') $ti = 3;
            }
            $bUrl = substr($path, 0, $ti);
            for ($i = $ti; $i < $pLen; $i++) {
                if (substr($path, $i, 1) == '\\' || substr($path, $i, 1) == '/') {
                    $bUrl = $bUrl . substr($path, $ti, $i - $ti);
                    if (!is_readable($bUrl)) mkdir($bUrl);
                    for ($j = $i + 1; $j < strlen($path) - 1; $j++) {
                        if (substr($path, $j, 1) == '\\' || substr($path, $j, 1) == '/') {
                            $i++;
                        } else {
                            break;
                        }
                    }
                    $ti = $i;
                }
            }
        }
    }

    $funName($fileUrl);
    if ($addBool) {
        file_put_contents($fileUrl, $str, FILE_APPEND);
    } else {
        file_put_contents($fileUrl, $str);
    }
    return $fileUrl;
};