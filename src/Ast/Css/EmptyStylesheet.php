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

namespace ScssPhp\ScssPhp\Ast\Css;

use League\Uri\Contracts\UriInterface;
use ScssPhp\ScssPhp\Serializer\Serializer;
use ScssPhp\ScssPhp\Visitor\CssVisitor;
use SourceSpan\FileSpan;
use SourceSpan\SourceFile;

/**
 * @internal
 */
final class EmptyStylesheet implements CssStylesheet
{
    private readonly FileSpan $span;

    public function __construct(?UriInterface $url = null)
    {
        $this->span = SourceFile::fromString('', $url)->span(0, 0);
    }

    public function getSpan(): FileSpan
    {
        return $this->span;
    }

    public function __toString(): string
    {
        return Serializer::serialize($this, true)->css;
    }

    public function getParent(): ?CssParentNode
    {
        return null;
    }

    public function isGroupEnd(): bool
    {
        return false;
    }

    public function accept(CssVisitor $visitor)
    {
        return $visitor->visitCssStylesheet($this);
    }

    public function isInvisible(): bool
    {
         return $this->accept(new IsInvisibleVisitor(includeBogus: true, includeComments: false));
    }

    public function isInvisibleOtherThanBogusCombinators(): bool
    {
        return $this->accept(new IsInvisibleVisitor(includeBogus: false, includeComments: true));
    }

    public function isInvisibleHidingComments(): bool
    {
        return $this->accept(new IsInvisibleVisitor(includeBogus: true, includeComments: true));
    }

    public function getChildren(): array
    {
        return [];
    }

    public function isChildless(): bool
    {
        return false;
    }
}
