<?php
return function ($fileUrl) {
    if (file_exists($fileUrl)) {
        try {
            unlink($fileUrl);
        } catch (\Exception $e) {
            // Nothing TODO
        }
    }

    return $fileUrl;
};