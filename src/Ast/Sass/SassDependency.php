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

namespace ScssPhp\ScssPhp\Ast\Sass;

use League\Uri\Contracts\UriInterface;
use ScssPhp\ScssPhp\Ast\Sass\Import\DynamicImport;
use ScssPhp\ScssPhp\Ast\Sass\Statement\ForwardRule;
use SourceSpan\FileSpan;

/**
 * A common interface for [UseRule]s, {@see ForwardRule}s, and {@see DynamicImport}s.
 *
 * @internal
 */
interface SassDependency extends SassNode
{
    /**
     * The URL of the dependency this rule loads.
     */
    public function getUrl(): UriInterface;

    /**
     * The span of the URL for this dependency, including the quotes.
     */
    public function getUrlSpan(): FileSpan;
}
