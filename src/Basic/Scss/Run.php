<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp\Scss;

class Run extends Compiler
{
    private static $compiler;

    public function __construct($cacheOptions = null)
    {
        parent::__construct($cacheOptions);
        $this->charsetSeen    = true;
    }

    /**
     * 获取 Compiler 实现
     * @return Compiler
     */
    private static function getCompiler() : Compiler
    {
        if (empty(self::$compiler)) {
            self::$compiler = new static();
        }
        return self::$compiler;
    }

    /**
     * 获取 Scss 解析代码
     * @param string $code
     * @param string $prevMessage
     * @return string
     */
    public static function getCode($code = '', $prevMessage = '')
    {
        try {
            $compiler = self::getCompiler();
            $retCode =  $compiler->compile($code)->getCss();

            $retCode = str_replace("/*", "\n/*", $retCode);
            $retCode = str_replace("*/", "*/\n", $retCode);

            return trim($retCode);
            
        } catch (\Exception $e) {
            // TODO
            $errors = [];
            if (!empty($prevMessage) && is_string($prevMessage)) {
                $prevMessage = trim($prevMessage);
                if (!empty($prevMessage)) {
                    $errors[] = $prevMessage;
                }
            }

            $errors[] = $e->getMessage();
            foreach ($errors as $index => $error) {
                $error = str_replace("/*", "\\/*", $error);
                $error = str_replace("*/", "*\\/", $error);
                $error = "/* {$error} */";
                $errors[$index] = $error;
            }

            return implode("\n", $errors);
        }
    }
}
