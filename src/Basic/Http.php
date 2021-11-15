<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Basic;

class Http
{
    /**
     * 获取文件编码
     * @param $string
     * @return string
     */
    private static function getEncode($string)
    {
        return mb_detect_encoding($string, ['ASCII', 'GB2312', 'GBK', 'UTF-8']);
    }

    /**
     * 获取URL地址和参数
     * @param string $url
     * @return array
     */
    public static function getUrlParams($url = '')
    {
        $params = [];
        $pos = strpos($url, '?');
        if ($pos === false) {
            return [$url, $params];
        }
        $urlExt = substr($url, $pos + 1);
        $url = substr($url, 0, $pos);
        $urlExtArr = explode("&", $urlExt);
        foreach ($urlExtArr as $uea) {
            $uea = trim($uea);
            if (empty($uea)) {
                continue;
            }
            list($k, $v) = explode("=", $uea);
            $k = trim($k);
            if (empty($k)) {
                continue;
            }
            if (isset($v)) {
                $params[$k] = $v;
            }
        }
        return [$url, $params];
    }

    /**
     * 获取正确的Header头信息
     * @param array $header
     * @return array
     */
    private static function getHeader($header = [])
    {
        $newHeader = [];
        if (empty($header) || !is_array($header)) {
            return $newHeader;
        }
        foreach ($header as $key => $val) {
            if (!is_string($val)) {
                continue;
            }
            if (is_int($key)) {
                $newHeader[] = $val;
            } else {
                $newHeader[] = $key . ": " . $val;
            }
        }
        return $newHeader;
    }

    /**
     * 远程获取数据，GET和POST模式
     * @param $url 指定URL完整路径地址
     * @param $para 请求的数据
     * @param $method GET或POST类型
     * @param null $header 请求头部信息
     * @param bool $outputEncoding 输出编码格式，如：utf-8
     * @param bool $isCurl 获取远程信息类型
     * @return bool|mixed|string
     */
    public static function getHttpData($url, $para = null, $method = null, $header = NULL, $outputEncoding = false, $isCurl = true)
    {
        $header = self::getHeader($header);
        if ($isCurl) {
            $method = strtolower($method);
            $curl = \curl_init();
            if ($method == 'get' || empty($para)) {
                if (!empty($para)) {
                    list($url, $data) = self::getUrlParams($url);
                    if (empty($data)) {
                        $data = $para;
                    } else {
                        foreach ($para as $key => $val) {
                            $data[$key] = $val;
                        }
                    }
                    $content = http_build_query($data);
                    $url .= "?{$content}";
                }
                \curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);//在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回
            } elseif ($method == 'json') {
                $paraString = json_encode($para);
                \curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                \curl_setopt($curl, CURLOPT_POSTFIELDS, $paraString);
                $header[] = 'Content-Type: application/json';
                $header[] = 'Content-Length: ' . strlen($paraString);
            } else {
                \curl_setopt($curl, CURLOPT_POST, count($para)); // post传输数据
                \curl_setopt($curl, CURLOPT_POSTFIELDS, $para);// post传输数据
            }

            \curl_setopt($curl, CURLOPT_URL, $url);
            \curl_setopt($curl, CURLOPT_HEADER, 0); // 过滤HTTP头

            \curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // 显示输出结果
            \curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
            \curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);  // 从证书中检查SSL加密算法是否存在
            if (!empty($header)) {
                \curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            }

            $responseText = curl_exec($curl);
//            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
        } else {
//            $httpCode = 0;
            $responseText = file_get_contents($url);

        }
        //设置编码格式
        if ($outputEncoding) {
            $htmlEncoding = self::getEncode($responseText);
            $responseText = iconv($htmlEncoding, $outputEncoding, $responseText);
        }

        return $responseText;
    }
}
