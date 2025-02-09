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

namespace ScssPhp\ScssPhp\Evaluation;

use ScssPhp\ScssPhp\Ast\AstNode;
use ScssPhp\ScssPhp\Ast\Sass\Statement\ForwardRule;
use ScssPhp\ScssPhp\Configuration;
use ScssPhp\ScssPhp\ConfiguredValue;
use ScssPhp\ScssPhp\Exception\MultiSpanSassScriptException;
use ScssPhp\ScssPhp\Exception\SassScriptException;
use ScssPhp\ScssPhp\Module\Module;
use ScssPhp\ScssPhp\SassCallable\SassCallable;
use ScssPhp\ScssPhp\SassCallable\UserDefinedCallable;
use ScssPhp\ScssPhp\Value\Value;
use SourceSpan\FileSpan;

/**
 * The lexical environment in which Sass is executed.
 *
 * This tracks lexically-scoped information, such as variables, functions, and
 * mixins.
 *
 * @internal
 */
final class Environment
{
    /**
     * The modules used in the current scope, indexed by their namespaces.
     *
     * @var \ArrayObject<string, Module>
     */
    private \ArrayObject $modules;

    /**
     * A map from module namespaces to the nodes whose spans indicate where those
     * modules were originally loaded.
     *
     * @var \ArrayObject<string, AstNode>
     */
    private \ArrayObject $namespaceNodes;

    // TODO figure out the right storage for global, imported and forwarded modules (maps indexed by modules)
    // TODO figure out the right storage for nested forwarded modules
    // TODO figure out the right storage for the list all modules (object or array?)

    /**
     * A list of variables defined at each lexical scope level.
     *
     * Each scope maps the names of declared variables to their values.
     *
     * The first element is the global scope, and each successive element is
     * deeper in the tree.
     *
     * @var array<int, \ArrayObject<string, Value>>
     */
    private array $variables;

    /**
     * The nodes where each variable in {@see variables} was defined.
     *
     * This stores {@see AstNode}s rather than {@see FileSpan}s so it can avoid calling
     * {@see AstNode::getSspan} if the span isn't required, since some nodes need to do
     * real work to manufacture a source span.
     *
     * @var array<int, \ArrayObject<string, AstNode>>
     */
    private array $variableNodes;

    /**
     * A map of variable names to their indices in {@see variables}.
     *
     * This map is filled in as-needed, and may not be complete.
     *
     * @var array<string, int>
     */
    private array $variableIndices = [];

    /**
     * A list of functions defined at each lexical scope level.
     *
     * Each scope maps the names of declared functions to their values.
     *
     * The first element is the global scope, and each successive element is
     * deeper in the tree.
     *
     * @var array<int, \ArrayObject<string, SassCallable>>
     */
    private array $functions;

    /**
     * A map of function names to their indices in {@see functions}.
     *
     * This map is filled in as-needed, and may not be complete.
     *
     * @var array<string, int>
     */
    private array $functionIndices = [];

    /**
     * A list of mixins defined at each lexical scope level.
     *
     * Each scope maps the names of declared mixins to their values.
     *
     * The first element is the global scope, and each successive element is
     * deeper in the tree.
     *
     * @var array<int, \ArrayObject<string, SassCallable>>
     */
    private array $mixins;

    /**
     * A map of mixin names to their indices in {@see mixins}.
     *
     * This map is filled in as-needed, and may not be complete.
     *
     * @var array<string, int>
     */
    private array $mixinIndices = [];

    /**
     * The content block passed to the lexically-enclosing mixin, or `null` if
     * this is not in a mixin, or if no content block was passed.
     */
    private ?UserDefinedCallable $content;

    /**
     * Whether the environment is lexically within a mixin.
     */
    private bool $inMixin = false;

    /**
     * Whether the environment is currently in a global or semi-global scope.
     *
     * A semi-global scope can assign to global variables, but it doesn't declare
     * them by default.
     */
    private bool $inSemiGlobalScope = true;

    /**
     * The name of the last variable that was accessed.
     *
     * This is cached to speed up repeated references to the same variable, as
     * well as references to the last variable's {@see FileSpan}.
     */
    private ?string $lastVariableName = null;

    /**
     * The index in {@see variables} of the last variable that was accessed.
     */
    private ?int $lastVariableIndex = null;

    public static function create(): Environment
    {
        return new Environment(new \ArrayObject(), new \ArrayObject(), [new \ArrayObject()], [new \ArrayObject()], [new \ArrayObject()], [new \ArrayObject()]);
    }

    /**
     * @param \ArrayObject<string, Module> $modules
     * @param \ArrayObject<string, AstNode> $namespaceNodes
     * @param array<int, \ArrayObject<string, Value>>        $variables
     * @param array<int, \ArrayObject<string, AstNode>>      $variableNodes
     * @param array<int, \ArrayObject<string, SassCallable>> $functions
     * @param array<int, \ArrayObject<string, SassCallable>> $mixins
     */
    private function __construct(\ArrayObject $modules, \ArrayObject $namespaceNodes, array $variables, array $variableNodes, array $functions, array $mixins, ?UserDefinedCallable $content = null)
    {
        $this->modules = $modules;
        $this->namespaceNodes = $namespaceNodes;
        $this->variables = $variables;
        $this->variableNodes = $variableNodes;
        $this->functions = $functions;
        $this->mixins = $mixins;
        $this->content = $content;
    }

    public function getContent(): ?UserDefinedCallable
    {
        return $this->content;
    }

    /**
     * Whether the environment is lexically at the root of the document.
     */
    public function atRoot(): bool
    {
        return \count($this->variables) === 1;
    }

    public function isInMixin(): bool
    {
        return $this->inMixin;
    }

    /**
     * Creates a closure based on this environment.
     *
     * Any scope changes in this environment will not affect the closure.
     * However, any new declarations or assignments in scopes that are visible
     * when the closure was created will be reflected.
     */
    public function closure(): Environment
    {
        return new Environment($this->modules, $this->namespaceNodes, $this->variables, $this->variableNodes, $this->functions, $this->mixins, $this->content);
    }

    /**
     * Returns a new environment to use for an imported file.
     *
     * The returned environment shares this environment's variables, functions,
     * and mixins, but excludes most modules (except for global modules that
     * result from importing a file with forwards).
     */
    public function forImport(): Environment
    {
        return new Environment(new \ArrayObject(), new \ArrayObject(), $this->variables, $this->variableNodes, $this->functions, $this->mixins, $this->content);
    }

    /**
     * Adds $module to the set of modules visible in this environment.
     *
     * $nodeWithSpan's span is used to report any errors with the module.
     *
     * If $namespace is passed, the module is made available under that
     * namespace.
     *
     * @throws SassScriptException if there's already a module with the given
     * $namespace, or if $namespace is `null` and $module defines a variable
     * with the same name as a variable defined in this environment.
     */
    public function addModule(Module $module, AstNode $nodeWithSpan, ?string $namespace = null): void
    {
        if ($namespace === null) {
            // TODO add to global and all modules

            foreach ($this->variables[0] as $name => $_) {
                if (isset($module->getVariables()[$name])) {
                    throw new SassScriptException("This module and the new module both define a variable named \$$name.");
                }
            }
        } else {
            if (isset($this->modules[$namespace])) {
                $span = ($this->namespaceNodes[$namespace] ?? null)?->getSpan();
                $secondarySpans = $span === null ? [] : ['original @use' => $span];

                throw new MultiSpanSassScriptException("There's already a module with namespace \"$namespace\".", 'new @use', $secondarySpans);
            }

            $this->modules[$namespace] = $module;
            $this->namespaceNodes[$namespace] = $nodeWithSpan;
            // TODO register in allModules
        }
    }

    /**
     * Exposes the members in $module to downstream modules as though they were
     * defined in this module, according to the modifications defined by $rule.
     */
    public function forwardModule(Module $module, ForwardRule $rule): void
    {
        // TODO
    }

    /**
     * Makes the members forwarded by $module available in the current
     * environment.
     *
     * This is called when $module is `@import`ed.
     */
    public function importForwards(Module $module): void
    {
        // TODO
    }

    public function getVariable(string $name, ?string $namespace = null): ?Value
    {
        if ($namespace !== null) {
            return $this->getModule($namespace)->getVariables()[$name] ?? null;
        }

        if ($this->lastVariableName === $name) {
            assert($this->lastVariableIndex !== null);

            return $this->variables[$this->lastVariableIndex][$name] ?? $this->getVariableFromGlobalModule($name);
        }

        $index = $this->variableIndices[$name] ?? null;

        if ($index !== null) {
            $this->lastVariableName = $name;
            $this->lastVariableIndex = $index;

            return $this->variables[$index][$name] ?? $this->getVariableFromGlobalModule($name);
        }

        $index = $this->variableIndex($name);

        if ($index !== null) {
            $this->lastVariableName = $name;
            $this->lastVariableIndex = $index;
            $this->variableIndices[$name] = $index;

            return $this->variables[$index][$name] ?? $this->getVariableFromGlobalModule($name);
        }

        // There isn't a real variable defined as this index, but it will cause
        // getVariable to short-circuit and get to this function faster next
        // time the variable is accessed.
        return $this->getVariableFromGlobalModule($name);
    }

    /**
     * Returns the value of the variable named $name from a namespaceless
     * module, or `null` if no such variable is declared in any namespaceless
     * module.
     */
    private function getVariableFromGlobalModule(string $name): ?Value
    {
        return $this->fromOneModule($name, 'variable', fn (Module $module) => $module->getVariables()[$name] ?? null);
    }

    /**
     * Returns the node for the variable named $name, or `null` if no such
     * variable is declared.
     *
     * This node is intended as a proxy for the {@see FileSpan} indicating where the
     * variable's value originated. It's returned as an {@see AstNode} rather than a
     * {@see FileSpan} so we can avoid calling {@see AstNode::getSpan()} if the span isn't
     * required, since some nodes need to do real work to manufacture a source
     * span.
     */
    public function getVariableNode(string $name, ?string $namespace = null): ?AstNode
    {
        if ($namespace !== null) {
            return $this->getModule($namespace)->getVariableNodes()[$name] ?? null;
        }

        if ($this->lastVariableName === $name) {
            assert($this->lastVariableIndex !== null);

            return $this->variableNodes[$this->lastVariableIndex][$name] ?? $this->getVariableNodeFromGlobalModule($name);
        }

        $index = $this->variableIndices[$name] ?? null;

        if ($index !== null) {
            $this->lastVariableName = $name;
            $this->lastVariableIndex = $index;

            return $this->variableNodes[$index][$name] ?? $this->getVariableNodeFromGlobalModule($name);
        }

        $index = $this->variableIndex($name);

        if ($index !== null) {
            $this->lastVariableName = $name;
            $this->lastVariableIndex = $index;
            $this->variableIndices[$name] = $index;

            return $this->variableNodes[$index][$name] ?? $this->getVariableNodeFromGlobalModule($name);
        }

        return $this->getVariableNodeFromGlobalModule($name);
    }

    /**
     * Returns the node for the variable named $name from a namespaceless
     * module, or `null` if no such variable is declared.
     *
     *  This node is intended as a proxy for the {@see FileSpan} indicating where the
     *  variable's value originated. It's returned as an {@see AstNode} rather than a
     *  {@see FileSpan} so we can avoid calling {@see AstNode::getSpan()} if the span isn't
     *  required, since some nodes need to do real work to manufacture a source
     *  span.
     */
    private function getVariableNodeFromGlobalModule(string $name): ?AstNode
    {
        // TODO

        return null;
    }

    /**
     * Returns whether a variable named $name exists.
     */
    public function variableExists(string $name): bool
    {
        return $this->getVariable($name) !== null;
    }

    /**
     * Returns whether a global variable named $name exists.
     */
    public function globalVariableExists(string $name, ?string $namespace = null): bool
    {
        if ($namespace !== null) {
            return isset($this->getModule($namespace)->getVariables()[$name]);
        }

        if (isset($this->variables[0][$name])) {
            return true;
        }

        return $this->getVariableFromGlobalModule($name) !== null;
    }

    /**
     * Returns the index of the last map in {@see variables} that has a $name key,
     * or `null` if none exists.
     */
    private function variableIndex(string $name): ?int
    {
        for ($i = \count($this->variables) - 1; $i >= 0; $i--) {
            if (isset($this->variables[$i][$name])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Sets the variable named $name to $value.
     *
     * If $global is `true`, this sets the variable at the top-level scope.
     * Otherwise, if the variable was already defined, it'll set it in the
     * previous scope. If it's undefined, it'll set it in the current scope.
     */
    public function setVariable(string $name, Value $value, AstNode $nodeWithSpan, bool $global = false, ?string $namespace = null): void
    {
        if ($namespace !== null) {
            $this->getModule($namespace)->setVariable($name, $value, $nodeWithSpan);

            return;
        }

        if ($global || $this->atRoot()) {
            // Don't set the index if there's already a variable with the given name,
            // since local accesses should still return the local variable.
            if (!isset($this->variableIndices[$name])) {
                $this->lastVariableName = $name;
                $this->lastVariableIndex = 0;
                $this->variableIndices[$name] = 0;
            }

            // If this module doesn't already contain a variable named $name, try
            // setting it in a global module.
            if (!isset($this->variables[0][$name])) {
                $moduleWithName = $this->fromOneModule($name, 'variable', fn (Module $module) => isset($module->getVariables()[$name]) ? $module : null);

                if ($moduleWithName !== null) {
                    $moduleWithName->setVariable($name, $value, $nodeWithSpan);

                    return;
                }
            }

            $this->variables[0][$name] = $value;
            $this->variableNodes[0][$name] = $nodeWithSpan;
            return;
        }

        // TODO handle nested forwarded modules

        if ($this->lastVariableName === $name) {
            assert($this->lastVariableIndex !== null);
            $index = $this->lastVariableIndex;
        } else {
            if (!isset($this->variableIndices[$name])) {
                $this->variableIndices[$name] = $this->variableIndex($name) ?? \count($this->variables) - 1;
            }
            $index = $this->variableIndices[$name];
        }

        if (!$this->inSemiGlobalScope && $index === 0) {
            $index = \count($this->variables) - 1;
            $this->variableIndices[$name] = $index;
        }

        $this->lastVariableName = $name;
        $this->lastVariableIndex = $index;
        $this->variables[$index][$name] = $value;
        $this->variableNodes[$index][$name] = $nodeWithSpan;
    }

    /**
     * Sets the variable named $name to $value.
     *
     * Unlike {@see setVariable}, this will declare the variable in the current scope
     * even if a declaration already exists in an outer scope.
     */
    public function setLocalVariable(string $name, Value $value, AstNode $nodeWithSpan): void
    {
        $index = \count($this->variables) - 1;
        $this->lastVariableName = $name;
        $this->lastVariableIndex = $index;
        $this->variableIndices[$name] = $index;
        $this->variables[$index][$name] = $value;
        $this->variableNodes[$index][$name] = $nodeWithSpan;
    }

    public function getFunction(string $name, ?string $namespace = null): ?SassCallable
    {
        if ($namespace !== null) {
            return $this->getModule($namespace)->getFunctions()[$name] ?? null;
        }

        $index = $this->functionIndices[$name] ?? null;

        if ($index !== null) {
            return $this->functions[$index][$name] ?? $this->getFunctionFromGlobalModule($name);
        }

        $index = $this->functionIndex($name);
        if ($index !== null) {
            $this->functionIndices[$name] = $index;

            return $this->functions[$index][$name] ?? $this->getFunctionFromGlobalModule($name);
        }

        return $this->getFunctionFromGlobalModule($name);
    }

    /**
     * Returns the value of the function named $name from a namespaceless
     * module, or `null` if no such function is declared in any namespaceless
     * module.
     */
    private function getFunctionFromGlobalModule(string $name): ?SassCallable
    {
        return $this->fromOneModule($name, 'function', fn (Module $module) => $module->getFunctions()[$name] ?? null);
    }

    /**
     * Returns the index of the last map in {@see functions} that has a $name key,
     * or `null` if none exists.
     */
    private function functionIndex(string $name): ?int
    {
        for ($i = \count($this->functions) - 1; $i >= 0; $i--) {
            if (isset($this->functions[$i][$name])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Returns whether a function named $name exists.
     */
    public function functionExists(string $name, ?string $namespace = null): bool
    {
        return $this->getFunction($name, $namespace) !== null;
    }

    public function setFunction(SassCallable $callable): void
    {
        $index = \count($this->functions) - 1;
        $name = $callable->getName();
        $this->functionIndices[$name] = $index;
        $this->functions[$index][$name] = $callable;
    }

    public function getMixin(string $name, ?string $namespace = null): ?SassCallable
    {
        if ($namespace !== null) {
            return $this->getModule($namespace)->getMixins()[$name] ?? null;
        }

        $index = $this->mixinIndices[$name] ?? null;

        if ($index !== null) {
            return $this->mixins[$index][$name] ?? $this->getMixinFromGlobalModule($name);
        }

        $index = $this->mixinIndex($name);
        if ($index !== null) {
            $this->mixinIndices[$name] = $index;

            return $this->mixins[$index][$name] ?? $this->getMixinFromGlobalModule($name);
        }

        return $this->getMixinFromGlobalModule($name);
    }

    /**
     * Returns the value of the mixin named $name from a namespaceless
     * module, or `null` if no such mixin is declared in any namespaceless
     * module.
     */
    private function getMixinFromGlobalModule(string $name): ?SassCallable
    {
        return $this->fromOneModule($name, 'mixin', fn (Module $module) => $module->getMixins()[$name] ?? null);
    }

    /**
     * Returns the index of the last map in {@see mixins} that has a $name key,
     * or `null` if none exists.
     */
    private function mixinIndex(string $name): ?int
    {
        for ($i = \count($this->mixins) - 1; $i >= 0; $i--) {
            if (isset($this->mixins[$i][$name])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Returns whether a mixin named $name exists.
     */
    public function mixinExists(string $name, ?string $namespace = null): bool
    {
        return $this->getMixin($name, $namespace) !== null;
    }

    public function setMixin(SassCallable $callable): void
    {
        $index = \count($this->mixins) - 1;
        $name = $callable->getName();
        $this->mixinIndices[$name] = $index;
        $this->mixins[$index][$name] = $callable;
    }

    /**
     * Sets $content as {@see content} for the duration of $callback.
     *
     * @param callable(): void $callback
     *
     * @param-immediately-invoked-callable $callback
     */
    public function withContent(?UserDefinedCallable $content, callable $callback): void
    {
        $oldContent = $this->content;
        $this->content = $content;
        $callback();
        $this->content = $oldContent;
    }

    /**
     * Sets {@see inMixin} to `true` for the duration of $callback.
     *
     * @param callable(): void $callback
     *
     * @param-immediately-invoked-callable $callback
     */
    public function asMixin(callable $callback): void
    {
        $oldInMixin = $this->inMixin;
        $this->inMixin = true;
        $callback();
        $this->inMixin = $oldInMixin;
    }

    /**
     * Runs $callback in a new scope.
     *
     * Variables, functions, and mixins declared in a given scope are
     * inaccessible outside of it. If $semiGlobal is passed, this scope can
     * assign to global variables without a `!global` declaration.
     *
     * If $when is false, this doesn't create a new scope and instead just
     * executes $callback and returns its result.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @param-immediately-invoked-callable $callback
     */
    public function scope(callable $callback, bool $when = true, bool $semiGlobal = false)
    {
        // We have to track semi-globalness even if `!$when` so that
        //
        //     div {
        //       @if ... {
        //         $x: y;
        //       }
        //     }
        //
        // doesn't assign to the global scope.
        $semiGlobal = $semiGlobal && $this->inSemiGlobalScope;
        $wasInSemiGlobalScope = $this->inSemiGlobalScope;
        $this->inSemiGlobalScope = $semiGlobal;

        if (!$when) {
            try {
                return $callback();
            } finally {
                $this->inSemiGlobalScope = $wasInSemiGlobalScope;
            }
        }

        $this->variables[] = new \ArrayObject();
        $this->variableNodes[] = new \ArrayObject();
        $this->functions[] = new \ArrayObject();
        $this->mixins[] = new \ArrayObject();

        try {
            return $callback();
        } finally {
            $this->inSemiGlobalScope = $wasInSemiGlobalScope;
            $this->lastVariableName = null;
            $this->lastVariableIndex = null;

            $removedVariables = array_pop($this->variables);
            assert($removedVariables !== null);
            foreach ($removedVariables as $name => $_) {
                unset($this->variableIndices[$name]);
            }
            array_pop($this->variableNodes);

            $removedFunctions = array_pop($this->functions);
            assert($removedFunctions !== null);
            foreach ($removedFunctions as $name => $_) {
                unset($this->functionIndices[$name]);
            }

            $removedMixins = array_pop($this->mixins);
            assert($removedMixins !== null);
            foreach ($removedMixins as $name => $_) {
                unset($this->mixinIndices[$name]);
            }
        }
    }

    /**
     * Creates an implicit configuration from the variables declared in this
     * environment.
     */
    public function toImplicitConfiguration(): Configuration
    {
        $configuration = [];

        foreach ($this->variables as $i => $values) {
            $nodes = $this->variableNodes[$i];

            foreach ($values as $name => $value) {
                \assert($nodes[$name] !== null);
                $configuration[$name] = ConfiguredValue::implicit($value, $nodes[$name]);
            }
        }

        return Configuration::implicit($configuration);
    }

    // TODO

    /**
     * Returns the module with the given namespace.
     *
     * @throws SassScriptException if none exists
     */
    private function getModule(string $namespace): Module
    {
        return $this->modules[$namespace] ?? throw new SassScriptException("There is no module with the namespace \"$namespace\".");
    }

    /**
     * Returns the result of $callback if it returns non-`null` for exactly one
     * module in {@see globalModules} *or* for any module in {@see importedModules} or
     * {@see nestedForwardedModules}.
     *
     * Returns `null` if $callback returns `null` for all modules. Throws an
     * error if $callback returns non-`null` for more than one module.
     *
     * The $name is the name of the member being looked up.
     *
     * The $type should be the singular name of the value type being returned.
     * It's used to format an appropriate error message.
     *
     * @template T
     *
     * @param callable(Module): (T|null) $callback
     *
     * @return T|null
     *
     * @param-immediately-invoked-callable $callback
     */
    private function fromOneModule(string $name, string $type, callable $callback): mixed
    {
        // TODO
        return null;
    }
}
