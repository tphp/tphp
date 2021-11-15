<?php
return function ($fileUrl) {
    $funName = "func_file_read";
    if (!function_exists($funName)) {
        function func_file_read($file)
        {  //判断文件是否存在
            if (preg_match('/^http:\/\//', $file)) {
                //远程文件
                if (ini_get('allow_url_fopen')) {
                    if (@fopen($file, 'r')) return true;
                } else {
                    $parseUrl = parse_url($file);
                    $host = $parseUrl['host'];
                    $path = $parseUrl['path'];
                    $fp = fsockopen($host, 80, $errno, $errStr, 10);
                    if (!$fp) return false;
                    fputs($fp, "GET {$path} HTTP/1.1 \r\nhost:{$host}\r\n\r\n");
                    if (preg_match('/HTTP\/1.1 200/', fgets($fp, 1024))) return true;
                }
                return false;
            }
            return file_exists($file);
        }
    }


    $str = "";
    if ($funName($fileUrl)) {
        $fileHandle = fopen($fileUrl, "r");
        while (!feof($fileHandle)) {
            $line = fgets($fileHandle);
            $str = $str . $line;
        }
        fclose($fileHandle);
    }
    return $str;
};