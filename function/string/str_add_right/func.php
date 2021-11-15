<?php
return function ($str, $addStr) {
    empty($str) && $str != 0 && $str = "";
    empty($addStr) && $addStr != 0 && $addStr = "";
    return $str . $addStr;
};