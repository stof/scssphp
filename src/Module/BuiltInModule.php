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
use League\Uri\Uri;
use ScssPhp\ScssPhp\Ast\AstNode;
use ScssPhp\ScssPhp\Ast\Css\CssStylesheet;
use ScssPhp\ScssPhp\Ast\Css\EmptyStylesheet;
use ScssPhp\ScssPhp\Exception\SassScriptException;
use ScssPhp\ScssPhp\Extend\EmptyExtensionStore;
use ScssPhp\ScssPhp\Extend\ExtensionStore;
use ScssPhp\ScssPhp\Module\Module;
use ScssPhp\ScssPhp\SassCallable\SassCallable;
use ScssPhp\ScssPhp\Value\Value;

/**
 * A module provided by Sass, available under the special `sass:` URL space.
 *
 * @internal
 */
final class BuiltInModule implements Module
{
    private readonly UriInterface $url;
    /**
     * @var array<string, SassCallable>
     */
    private array $functions;
    /**
     * @var array<string, SassCallable>
     */
    private readonly array $mixins;
    /**
     * @var array<string, Value>
     */
    private readonly array $variables;

    // TODO add a way to lazily hydrate functions from the registry instead of doing all parsing eagerly

    /**
     * @param SassCallable[] $functions
     * @param SassCallable[] $mixins
     * @param array<string, Value> $variables
     */
    public function __construct(string $name, array $functions = [], array $mixins = [], array $variables = [])
    {
        $this->url = Uri::fromComponents(['scheme' => 'sass', 'path' => $name]);
        $this->functions = self::callableMap($functions);
        $this->mixins = self::callableMap($mixins);
        $this->variables = $variables;
    }

    /**
     * @param SassCallable[] $callables
     *
     * @return array<string, SassCallable>
     */
    private static function callableMap(array $callables): array
    {
        $map = [];

        foreach ($callables as $callable) {
            $map[$callable->getName()] = $callable;
        }

        return $map;
    }

    public function getUrl(): ?UriInterface
    {
        return $this->url;
    }

    public function getUpstream(): array
    {
        return [];
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getVariableNodes(): array
    {
        return [];
    }

    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function getMixins(): array
    {
        return $this->mixins;
    }

    public function getExtensionStore(): ExtensionStore
    {
        return new EmptyExtensionStore();
    }

    public function getCss(): CssStylesheet
    {
        return new EmptyStylesheet($this->url);
    }

    public function transitivelyContainsCss(): bool
    {
        return false;
    }

    public function transitivelyContainsExtensions(): bool
    {
        return false;
    }

    public function setVariable(string $name, Value $value, AstNode $nodeWithSpan): void
    {
        if (!isset($this->variables[$name])) {
            throw new SassScriptException('Undefined variable.');
        }

        throw new SassScriptException('Cannot modify built-in variable.');
    }

    public function variableIdentity(string $name): object
    {
        \assert(isset($this->variables[$name]));
        return $this;
    }

    public function cloneCss(): Module
    {
        return $this;
    }
}
