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

namespace ScssPhp\ScssPhp;

use ScssPhp\ScssPhp\Ast\AstNode;
use ScssPhp\ScssPhp\Value\Value;
use SourceSpan\FileSpan;

/**
 * A variable value that's been configured for a {@see Configuration}.
 *
 * @internal
 */
final class ConfiguredValue
{
    /**
     * The value of the variable.
     */
    public readonly Value $value;

    /**
     * The span where the variable's configuration was written, or `null` if this
     * value was configured implicitly.
     */
    public readonly ?FileSpan $configurationSpan;

    /**
     * The {@see AstNode} where the variable's value originated.
     */
    public readonly AstNode $assignmentNode;

    private function __construct(Value $value, AstNode $assignmentNode, ?FileSpan $configurationSpan = null)
    {
        $this->value = $value;
        $this->configurationSpan = $configurationSpan;
        $this->assignmentNode = $assignmentNode;
    }

    /**
     * Creates a variable value that's been configured explicitly with a `with`
     * clause.
     */
    public static function explicit(Value $value, FileSpan $configurationSpan, AstNode $assignmentNode): self
    {
        return new self($value, $assignmentNode, $configurationSpan);
    }

    /**
     * Creates a variable value that's implicitly configured by setting a
     * variable prior to an `@import` of a file that contains a `@forward`.
     */
    public static function implicit(Value $value, AstNode $assignmentNode): self
    {
        return new self($value, $assignmentNode);
    }
}
