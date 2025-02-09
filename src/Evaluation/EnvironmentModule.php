<?php

namespace ScssPhp\ScssPhp\Evaluation;

use League\Uri\Contracts\UriInterface;
use ScssPhp\ScssPhp\Ast\AstNode;
use ScssPhp\ScssPhp\Ast\Css\CssStylesheet;
use ScssPhp\ScssPhp\Extend\ExtensionStore;
use ScssPhp\ScssPhp\Module\Module;
use ScssPhp\ScssPhp\Value\Value;

/**
 * A module that represents the top-level members defined in an {@see Environment}.
 */
final class EnvironmentModule implements Module
{
    // TODO add types
    private $upstream;
    private $variables;
    private $variableNodes;
    private $functions;
    private $mixins;
    private readonly CssStylesheet $css;
    private readonly ExtensionStore $extensionStore;
    private $preModuleComments;
    private readonly bool $transitivelyContainsCss;
    private readonly bool $transitivelyContainsExtensions;
    /**
     * The environment that defines this module's members.
     */
    private readonly Environment $environment;

    /**
     * A map from variable names to the modules in which those variables appear,
     * used to determine where variables should be set.
     *
     * Variables that don't appear in this map are either defined directly in
     * this module (if they appear in `_environment._variables.first`) or not
     * defined at all.
     *
     * @var array<string, Module>
     */
    private array $modulesByVariable;

    public static function create(Environment $environment, CssStylesheet $cssStylesheet, $preModuleComments, ExtensionStore $extensionStore, array $forwarded = []): self
    {
        return new EnvironmentModule($environment, $cssStylesheet, $preModuleComments, $extensionStore, self::makeModulesByVariable($forwarded));
    }

    /**
     * @param Module[] $forwarded
     *
     * @return array<string, Module>
     */
    private static function makeModulesByVariable(array $forwarded): array
    {
        $modulesByVariable = [];

        foreach ($forwarded as $module) {
            if ($module instanceof EnvironmentModule) {
                // Flatten nested forwarded modules to avoid O(depth) overhead.
                foreach ($module->modulesByVariable as $child) {
                    foreach ($child->getVariables() as $name => $_) {
                        $modulesByVariable[$name] = $child;
                    }
                }

                // TODO implement a way to read the environment state from EnvironmentModule
                foreach ($module->environment->variables[0] as $name => $_) {
                    $modulesByVariable[$name] = $module;
                }
            } else {
                foreach ($module->getVariables() as $name => $_) {
                    $modulesByVariable[$name] = $module;
                }
            }
        }

        return $modulesByVariable;
    }

    /**
     * Returns a map that exposes the public members of $localMap as well as all
     * the members of $otherMaps.
     */
    private static function memberMap($localMap, $otherMaps)
    {
        // TODO
    }

    private function __construct(Environment $environment, CssStylesheet $css, $preModuleComments, ExtensionStore $extensionStore, array $modulesByVariable, $variables, $variableNodes, $functions, $mixins, bool $transitivelyContainsCss, bool $transitivelyContainsExtensions)
    {
        $this->environment = $environment;
        $this->css = $css;
        $this->preModuleComments = $preModuleComments;
        $this->extensionStore = $extensionStore;
        $this->modulesByVariable = $modulesByVariable;
        $this->variables = $variables;
        $this->variableNodes = $variableNodes;
        $this->functions = $functions;
        $this->mixins = $mixins;
        $this->transitivelyContainsCss = $transitivelyContainsCss;
        $this->transitivelyContainsExtensions = $transitivelyContainsExtensions;
    }

    public function getUrl(): ?UriInterface
    {
        return $this->css->getSpan()->getSourceUrl();
    }

    public function getUpstream(): array
    {
        return $this->upstream;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getVariableNodes(): array
    {
        return $this->variableNodes;
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
        return $this->extensionStore;
    }

    public function getCss(): CssStylesheet
    {
        return $this->css;
    }

    public function transitivelyContainsCss(): bool
    {
        return $this->transitivelyContainsCss;
    }

    public function transitivelyContainsExtensions(): bool
    {
        return $this->transitivelyContainsExtensions;
    }

    public function setVariable(string $name, Value $value, AstNode $nodeWithSpan): void
    {
        $module = $this->modulesByVariable[$name] ?? null;
        if ($module !== null) {
            $module->setVariable($name, $value, $nodeWithSpan);
            return;
        }

        // TODO: Implement setVariable() method.
    }

    public function variableIdentity(string $name): object
    {
        \assert(isset($this->variables[$name]));
        $module = $this->modulesByVariable[$name] ?? null;

        return $module === null ? $this : $module->variableIdentity($name);
    }

    public function cloneCss(): Module
    {
        if (!$this->transitivelyContainsCss) {
            return $this;
        }

        [$newExtensionStore, $oldToNewSelectors] = $this->extensionStore->clone();

        $newStylesheet = (new CloneCssVisitor($oldToNewSelectors))->visitCssStylesheet($this->css);

        return new EnvironmentModule($this->environment, $newStylesheet, $this->preModuleComments, $newExtensionStore, $this->modulesByVariable, $this->variables, $this->variableNodes, $this->functions, $this->mixins, $this->transitivelyContainsCss, $this->transitivelyContainsExtensions);
    }
}
