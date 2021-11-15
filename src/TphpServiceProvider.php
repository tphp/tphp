<?php

/**
 * This file is part of the tphp/tphp library
 *
 * @link        http://github.com/tphp/tphp
 * @copyright   Copyright (c) 2021 TPHP (http://www.tphp.com)
 * @license     http://opensource.org/licenses/MIT MIT
 */

namespace Tphp;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class TphpServiceProvider extends ServiceProvider
{
    public function register()
    {
        error_reporting(E_ALL ^ E_NOTICE);
        \Tphp\Routes::set();
    }
}
