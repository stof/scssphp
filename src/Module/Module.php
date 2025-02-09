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

namespace ScssPhp\ScssPhp\Module;

use League\Uri\Contracts\UriInterface;
use ScssPhp\ScssPhp\Ast\AstNode;
use ScssPhp\ScssPhp\Ast\Css\CssStylesheet;
use ScssPhp\ScssPhp\Exception\SassScriptException;
use ScssPhp\ScssPhp\Extend\ExtensionStore;
use ScssPhp\ScssPhp\SassCallable\SassCallable;
use ScssPhp\ScssPhp\Value\Value;
use SourceSpan\FileSpan;

/**
 * The interface for a Sass module.
 *
 * @internal
 */
interface Module
{
    /**
     * The canonical URL for this module's source file.
     *
     * This may be `null` if the module was loaded from a string without a URL
     * provided.
     */
    public function getUrl(): ?UriInterface;

    // TODO check whether those return values should be represented with a PHP array or an object, depending on the way mutations should be reflected in them.
    /**
     * Modules that this module uses.
     *
     * @return list<Module>
     */
    public function getUpstream(): array;

    /**
     * The module's variables.
     *
     * @return array<string, Value>
     */
    public function getVariables(): array;

    /**
     * The nodes where each variable in {@see getVariables} was defined.
     *
     * This stores {@see AstNode}s rather than {@see FileSpan}s so it can avoid calling
     * {@see AstNode::getSpan()} if the span isn't required, since some nodes need to do
     * real work to manufacture a source span.
     *
     * Implementations must ensure that this has the same keys as {@see getVariables}.
     *
     * @return array<string, AstNode>
     */
    public function getVariableNodes(): array;

    /**
     * The module's functions.
     *
     * @return array<string, SassCallable>
     */
    public function getFunctions(): array;

    /**
     * The module's mixins.
     *
     * @return array<string, SassCallable>
     */
    public function getMixins(): array;

    /**
     * The extensions defined in this module, which is also able to update
     * {@see getCss}'s style rules in-place based on downstream extensions.
     */
    public function getExtensionStore(): ExtensionStore;

    /**
     * The module's CSS tree.
     */
    public function getCss(): CssStylesheet;

    // TODO add preModuleComments once figuring the right type for this map indexed by module

    /**
     * Whether this module *or* any modules in {@see getUpstream} contain any CSS.
     */
    public function transitivelyContainsCss(): bool;

    /**
     * Whether this module *or* any modules in {@see getUpstream} contain `@extend` rules.
     */
    public function transitivelyContainsExtensions(): bool;

    /**
     * Sets the variable named $name to $value, associated with
     * $nodeWithSpan's source span.
     *
     * This takes an {@see AstNode} rather than a {@see FileSpan} so it can avoid calling
     * {@see AstNode::getSpan()} if the span isn't required, since some nodes need to do
     * real work to manufacture a source span.
     *
     * @throws SassScriptException if this module doesn't define a variable named $name.
     */
    public function setVariable(string $name, Value $value, AstNode $nodeWithSpan): void;

    /**
     * Returns an opaque object that will be equal to another
     * `variableIdentity()` return value for the same name in another module if
     * and only if both modules expose identical definitions of the variable in
     * question, as defined by the Sass spec.
     */
    public function variableIdentity(string $name): object;

    /**
     * Creates a copy of this module with new {@see getCss} and {@see getExtensionStore}.
     */
    public function cloneCss(): Module;
}
