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

namespace ScssPhp\ScssPhp\Ast\Sass\Statement;

use ScssPhp\ScssPhp\Ast\Sass\Expression;
use ScssPhp\ScssPhp\Ast\Sass\Statement;
use ScssPhp\ScssPhp\SourceSpan\FileSpan;
use ScssPhp\ScssPhp\Visitor\StatementVisitor;

/**
 * A variable declaration.
 *
 * This defines or sets a variable.
 *
 * @internal
 */
final class VariableDeclaration implements Statement
{
    private $namespace;
    private $name;
    private $comment;
    private $expression;
    private $guarded;
    private $global;
    private $span;

    public function __construct(string $name, Expression $expression, FileSpan $span, ?string $namespace = null, bool $guarded = false, bool $global = false, ?SilentComment $comment = null)
    {
        $this->name = $name;
        $this->expression = $expression;
        $this->span = $span;
        $this->namespace = $namespace;
        $this->guarded = $guarded;
        $this->global = $global;
        $this->comment = $comment;

        if ($namespace !== null && $global) {
            throw new \InvalidArgumentException("Other modules' members can't be defined with !global.");
        }
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getComment(): ?SilentComment
    {
        return $this->comment;
    }

    public function getExpression(): Expression
    {
        return $this->expression;
    }

    public function isGuarded(): bool
    {
        return $this->guarded;
    }

    public function isGlobal(): bool
    {
        return $this->global;
    }

    public function getSpan(): FileSpan
    {
        return $this->span;
    }

    public function accepts(StatementVisitor $visitor)
    {
        return $visitor->visitVariableDeclaration($this);
    }
}
