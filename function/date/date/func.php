<?php

return function ($time = null, $format = 'Y-m-d H:i:s', $isEmpty = false) {
    if ($isEmpty) {
        if (empty($time) || $time == 0) {
            return '';
        }
    }
    if ($time === null || $time === "") $time = time();
    $timeStr = $time . "";
    if (strlen($timeStr) > 10) {
        $time = substr($timeStr, 0, 10);
    }
    return date($format, $time);
};