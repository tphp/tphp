<?php
return function ($num, $decimals = 0, $decPoint = '.', $thousandsSep = ',') {
    return number_format($num, $decimals, $decPoint, $thousandsSep);
};