<?php

/**
 * SCSSPHP
 *
 * @copyright 2012-2020 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://scssphp.github.io/scssphp
 */

namespace Tphp\Scss;

/**
 * Block
 *
 * @author Anthon Pang <anthon.pang@gmail.com>
 */
class Block
{
    /**
     * @var string
     */
    public $type;

    /**
     * @var \Tphp\Scss\Block
     */
    public $parent;

    /**
     * @var string
     */
    public $sourceName;

    /**
     * @var integer
     */
    public $sourceIndex;

    /**
     * @var integer
     */
    public $sourceLine;

    /**
     * @var integer
     */
    public $sourceColumn;

    /**
     * @var array|null
     */
    public $selectors;

    /**
     * @var array
     */
    public $comments;

    /**
     * @var array
     */
    public $children;

    /**
     * @var \Tphp\Scss\Block
     */
    public $selfParent;
}
