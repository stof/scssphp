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

/**
 * A {@see Configuration} that was created with an explicit `with` clause of a
 * `@use` rule.
 *
 * Both types of configuration pass through `@forward` rules, but explicit
 * configurations will cause an error if attempting to use them on a module
 * that has already been loaded, while implicit configurations will be
 * silently ignored in this case.
 *
 * @internal
 */
final class ExplicitConfiguration extends Configuration
{
    /**
     * The node whose span indicates where the configuration was declared.
     */
    public readonly AstNode $nodeWithSpan;

    /**
     * Creates a base {@see ExplicitConfiguration} with a $values map and a
     * $nodeWithSpan.
     *
     * @param array<string, ConfiguredValue> $values
     */
    public static function create(array $values, AstNode $nodeWithSpan): self
    {
        return new self($values, $nodeWithSpan);
    }

    /**
     * @param array<string, ConfiguredValue> $values
     */
    protected function __construct(array $values, AstNode $nodeWithSpan, ?Configuration $originalConfiguration = null)
    {
        $this->nodeWithSpan = $nodeWithSpan;
        parent::__construct($values, $originalConfiguration);
    }

    protected function withValues(array $values): Configuration
    {
        return new self($values, $this->nodeWithSpan, $this->getOriginalConfiguration());
    }
}
