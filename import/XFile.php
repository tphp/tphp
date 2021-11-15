<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

class XFile
{
    /**
     * 创建文件夹
     * @param $urlRoot
     */
    public function mkDir($urlRoot)
    {
        if (!is_string($urlRoot)) {
            return;
        }

        if (is_readable($urlRoot)) {
            return;
        }

        $urlRoot = str_replace("\\", "/", $urlRoot);
        $urArr = explode("/", $urlRoot);
        $urLastIndex = count($urArr) - 1;
        if ($urArr[$urLastIndex] !== '') {
            unset($urArr[$urLastIndex]);
        }

        if (count($urArr) <= 0) {
            return;
        }

        $urlTmp = $urArr[0];
        unset($urArr[0]);

        foreach ($urArr as $ua) {
            if ($ua == '') {
                continue;
            }
            $urlTmp .= "/{$ua}";
            if (is_readable($urlTmp)) {
                if (!is_dir($urlTmp)) {
                    break;
                }
                continue;
            }

            @mkdir($urlTmp);

            if (!is_dir($urlTmp)) {
                break;
            }
        }
    }

    /**
     * 判断文件是否存在
     * @param $file
     * @return bool
     */
    public function isFileExists($file)
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

    /**
     * 写入文件
     * @param $urlRoot
     * @param $str
     * @param bool $addBool 是否追加
     */
    public function write($urlRoot, $str, $addBool = false)
    {
        $this->mkDir($urlRoot);
        if ($addBool) {
            file_put_contents($urlRoot, $str, FILE_APPEND);
        } else {
            file_put_contents($urlRoot, $str);
        }
    }

    /**
     * 读取文件
     * @param $fileUrl
     * @return string
     */
    public function read($fileUrl)
    {
        $str = "";
        if ($this->isFileExists($fileUrl)) {
            $fileHandle = fopen($fileUrl, "r");
            while (!feof($fileHandle)) {
                $line = fgets($fileHandle);
                $str .= $line;
            }
            fclose($fileHandle);
        }
        return $str;
    }

    /**
     * 删除文件
     * @param $fileUrl
     */
    public function delete($path)
    {

        try {
            if (is_link($path)) {
                unlink($path);
            } else {
                if (PHP_OS == 'WINNT') {
                    $path = str_replace("/", "\\", $path);
                    if (is_file($path)) {
                        unlink($path);
                    } else {
                        @rmdir($path);
                    }
                } else {
                    unlink($path);
                }
            }
        } catch (\Exception $e) {
            // Nothing TODO
        }
    }

    /**
     * 清空文件夹函数和清空文件夹后删除空文件夹函数的处理
     * @param $path
     */
    public function deleteDir($path)
    {
        $path = rtrim($path, "/");
        if (is_link($path) || is_file($path)) {
            $this->delete($pDir);
        } elseif (is_dir($path)) {
            //如果是目录则继续
            //扫描一个文件夹内的所有文件夹和文件并返回数组
            $p = scandir($path);
            foreach ($p as $val) {
                //排除目录中的.和..
                if ($val != "." && $val != "..") {
                    //如果是目录则递归子目录，继续操作
                    $pDir = $path . "/" . $val;
                    if (is_link($pDir) || is_file($pDir)) {
                        $this->delete($pDir);
                    } elseif (is_dir($pDir)) {
                        //子目录中操作删除文件夹和文件
                        $this->deleteDir($pDir . '/');
                        //目录清空后删除空文件夹
                        @rmdir($pDir);
                    } else {
                        $this->delete($pDir);
                    }
                }
            }
            @rmdir($path);
        }
    }

    /**
     * 获取文件夹及文件
     * @param string $dirRoot
     * @param string $child
     * @param array $noDirs
     * @param array $dirs
     * @return array
     */
    private function __getAllDirs($dirRoot = "", $child = "", $noDirs = [], &$dirs = [])
    {
        $dir = $dirRoot;
        !empty($child) && $dir .= DIRECTORY_SEPARATOR . $child;
        $files = scandir($dir);
        foreach ($files as $v) {
            $newPath = $dir . DIRECTORY_SEPARATOR . $v;
            if (is_dir($newPath) && $v != '.' && $v != '..') {
                if (!empty($noDirs) && is_array($noDirs) && in_array($v, $noDirs)) {
                    continue;
                }
                $d = $v;
                !empty($child) && $d = $child . DIRECTORY_SEPARATOR . $d;
                $dirs[] = $d;
                $this->__getAllDirs($dirRoot, $d, $noDirs, $dirs);
            }
        }
        return $dirs;
    }

    /**
     * 获取文件夹下的所有文件夹
     * @param string $dirRoot
     * @param array $noDirs 排除路径
     * @return array
     */
    public function getAllDirs($dirRoot = "", $noDirs = [])
    {
        $dirRoot = rtrim(trim($dirRoot), "/");
        if (empty($dirRoot) || !is_dir($dirRoot)) return [];
        $allDirs = $this->__getAllDirs($dirRoot, "", $noDirs);
        foreach ($allDirs as $key => $val) {
            $allDirs[$key] = str_replace("\\", "/", $val);
        }
        return $allDirs;
    }

    /**
     * 获取所有文件
     * @param string $urlRoot
     * @return array|mixed
     */
    public function getAllFiles($urlRoot = "")
    {
        $urlRoot = rtrim(trim($urlRoot), "/");

        $files = $this->getDirsFiles($urlRoot)['files'];
        if (empty($files)) {
            $files = [];
        }

        $dirs = $this->getAllDirs($urlRoot);

        if (empty($dirs)) {
            return $files;
        }

        foreach ($dirs as $dir) {
            $_files = $this->getDirsFiles($urlRoot . "/" . $dir)['files'];
            if (!empty($_files)) {
                foreach ($_files as $_file) {
                    $files[] = $dir . "/" . $_file;
                }
            }
        }

        return $files;
    }

    /**
     * 获取文件夹及文件
     * @param string $urlRoot
     * @return array
     */
    public function getDirsFiles($urlRoot = "")
    {
        $urlRoot = rtrim(trim($urlRoot), "/");
        if (empty($urlRoot) || !is_dir($urlRoot)) return [];
        $files = scandir($urlRoot);
        $fileItem = [];
        foreach ($files as $v) {
            $newPath = $urlRoot . DIRECTORY_SEPARATOR . $v;
            if (is_dir($newPath) && $v != '.' && $v != '..') {
                $fileItem['dirs'][] = $v;
            } else if (is_file($newPath)) {
                $fileItem['files'][] = $v;
            }
        }
        return $fileItem;
    }

    /**
     * 用法：
     * xCopy("feiy","feiy2",1):拷贝feiy下的文件到 feiy2,包括子目录
     * xCopy("feiy","feiy2",0):拷贝feiy下的文件到 feiy2,不包括子目录
     * 参数说明：
     * $source:源目录名
     * $destination:目的目录名
     * $child:复制时，是不是包含的子目录
     */
    function copy($source, $destination, $child = true)
    {
        if (!is_dir($source)) {
            echo("Error:the $source is not a direction!");
            return 0;
        }


        if (!is_dir($destination)) {
            @mkdir($destination, 0777);
        }

        $handle = dir($source);
        while ($entry = $handle->read()) {
            if (($entry != ".") && ($entry != "..")) {
                if (is_dir($source . "/" . $entry)) {
                    if ($child)
                        $this->copy($source . "/" . $entry, $destination . "/" . $entry, $child);
                } else {
                    copy($source . "/" . $entry, $destination . "/" . $entry);
                }
            }
        }
        return 1;
    }

    /**
     * 获取文件夹
     * @param string $urlRoot
     * @return array|mixed
     */
    public function getDirs($urlRoot = "")
    {
        $df = $this->getDirsFiles($urlRoot);
        if (empty($df) || empty($df['dirs'])) return [];
        return $df['dirs'];
    }

    /**
     * 获取文件
     * @param string $urlRoot
     * @return array|mixed
     */
    public function getFiles($urlRoot = "")
    {
        $df = $this->getDirsFiles($urlRoot);
        if (empty($df) || empty($df['files'])) return [];
        return $df['files'];
    }

    public function getGzData($gzFile)
    {
        $gz = gzopen($gzFile, 'r');
        $sqlStr = "";
        while (true) {
            $sqlTmp = gzgets($gz);
            if (preg_match('/.*;$/', trim($sqlTmp))) {
                $sqlStr .= $sqlTmp;
            } elseif (substr(trim($sqlTmp), 0, 2) != '--' && !empty($sqlTmp)) {
                $sqlStr .= $sqlTmp;
            } elseif (gzeof($gz)) {
                break;
            }
        }
        return $sqlStr;
    }

    /**
     * 显示图片
     * @param $imageFile
     * @return bool|string
     */
    public function showImage($imageFile)
    {
        if (!file_exists($imageFile)) {
            return "Image Error !";
        }
        $info = getimagesize($imageFile);
        $imgForm = $info['mime'];
        $imgData = fread(fopen($imageFile, 'rb'), filesize($imageFile));
        __header("content-type:{$imgForm}");
        return $imgData;
    }

    /**
     * 获取图片base64编码字符串
     * @param $imageFile
     * @return string
     */
    public function getImageBase64($imageFile)
    {
        $imageInfo = getimagesize($imageFile);
        $imageData = fread(fopen($imageFile, 'r'), filesize($imageFile));
        $base64Image = 'data:' . $imageInfo['mime'] . ';base64,' . chunk_split(base64_encode($imageData));
        return $base64Image;
    }
}