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

use League\Uri\Contracts\UriInterface;
use ScssPhp\ScssPhp\Ast\Sass\ConfiguredVariable;
use ScssPhp\ScssPhp\Ast\Sass\Expression\StringExpression;
use ScssPhp\ScssPhp\Ast\Sass\SassDependency;
use ScssPhp\ScssPhp\Ast\Sass\Statement;
use ScssPhp\ScssPhp\Util\SpanUtil;
use ScssPhp\ScssPhp\Visitor\StatementVisitor;
use SourceSpan\FileSpan;

/**
 * A `@forward` rule.
 *
 * @internal
 */
final class ForwardRule implements Statement, SassDependency
{
    /**
     * The URI of the module to forward.
     *
     * If this is relative, it's relative to the containing file.
     */
    private readonly UriInterface $url;

    /**
     * The set of mixin and function names that may be accessed from the
     * forwarded module.
     *
     * If this is empty, no mixins or functions may be accessed. If it's `null`,
     * it imposes no restrictions on which mixins and function may be accessed.
     *
     * If this is non-`null`, {@see $hiddenMixinsAndFunctions} and {@see $hiddenVariables}
     * are guaranteed to both be `null` and {@see shownVariables} is guaranteed to be
     * non-`null`.
     *
     * @var list<string>|null
     */
    public readonly ?array $shownMixinsAndFunctions;

    /**
     * The set of variable names (without `$`) that may be accessed from the
     * forwarded module.
     *
     * If this is empty, no variables may be accessed. If it's `null`, it imposes
     * no restrictions on which variables may be accessed.
     *
     * If this is non-`null`, {@see $hiddenMixinsAndFunctions} and {@see $hiddenVariables}
     * are guaranteed to both be `null` and {@see $shownMixinsAndFunctions} is
     * guaranteed to be non-`null`.
     *
     * @var list<string>|null
     */
    public readonly ?array $shownVariables;

    /**
     * The set of mixin and function names that may not be accessed from the
     * forwarded module.
     *
     * If this is empty, any mixins or functions may be accessed. If it's `null`,
     * it imposes no restrictions on which mixins or functions may be accessed.
     *
     * If this is non-`null`, {@see $shownMixinsAndFunctions} and {@see $shownVariables} are
     * guaranteed to both be `null` and {@see $hiddenVariables} is guaranteed to be
     * non-`null`.
     *
     * @var list<string>|null
     */
    public readonly ?array $hiddenMixinsAndFunctions;

    /**
     * The set of variable names (without `$`) that may be accessed from the
     * forwarded module.
     *
     * If this is empty, any variables may be accessed. If it's `null`, it
     * imposes no restrictions on which variables may be accessed.
     *
     * If this is non-`null`, {@see $shownMixinsAndFunctions} and {@see $shownVariables} are
     * guaranteed to both be `null` and {@see $hiddenMixinsAndFunctions} is guaranteed
     * to be non-`null`.
     *
     * @var list<string>|null
     */
    public readonly ?array $hiddenVariables;

    /**
     * The prefix to add to the beginning of the names of members of the used
     * module, or `null` if member names are used as-is.
     */
    public readonly ?string $prefix;

    /**
     * A list of variable assignments used to configure the loaded modules.
     *
     * @var list<ConfiguredVariable>
     */
    public readonly array $configuration;

    private readonly FileSpan $span;

    /**
     * Creates a `@forward` rule that allows all members to be accessed.
     *
     * @param list<ConfiguredVariable>|null $configuration
     */
    public static function create(UriInterface $url, FileSpan $span, ?string $prefix = null, ?array $configuration = null): ForwardRule
    {
        return new ForwardRule($url, $prefix, $span, $configuration);
    }

    /**
     * Creates a `@forward` rule that allows only members included in
     * $shownMixinsAndFunctions and $shownVariables to be accessed.
     *
     * @param list<string> $shownMixinsAndFunctions
     * @param list<string> $shownVariables
     * @param list<ConfiguredVariable>|null $configuration
     */
    public static function show(UriInterface $url, array $shownMixinsAndFunctions, array $shownVariables, FileSpan $span, ?string $prefix = null, ?array $configuration = null): ForwardRule
    {
        return new ForwardRule($url, $prefix, $span, $configuration, $shownMixinsAndFunctions, $shownVariables);
    }

    /**
     * Creates a `@forward` rule that allows only members not included in
     * $hiddenMixinsAndFunctions and $hiddenVariables to be accessed.
     *
     * @param list<string> $hiddenMixinsAndFunctions
     * @param list<string> $hiddenVariables
     * @param list<ConfiguredVariable>|null $configuration
     */
    public static function hide(UriInterface $url, array $hiddenMixinsAndFunctions, array $hiddenVariables, FileSpan $span, ?string $prefix = null, ?array $configuration = null): ForwardRule
    {
        return new ForwardRule($url, $prefix, $span, $configuration, hiddenMixinsAndFunctions: $hiddenMixinsAndFunctions, hiddenVariables: $hiddenVariables);
    }

    /**
     * @param list<ConfiguredVariable>|null $configuration
     * @param list<string>|null $shownMixinsAndFunctions
     * @param list<string>|null $shownVariables
     * @param list<string>|null $hiddenMixinsAndFunctions
     * @param list<string>|null $hiddenVariables
     */
    private function __construct(UriInterface $url, ?string $prefix, FileSpan $span, ?array $configuration, ?array $shownMixinsAndFunctions = null, ?array $shownVariables = null, ?array $hiddenMixinsAndFunctions = null, ?array $hiddenVariables = null)
    {
        $this->url = $url;
        $this->prefix = $prefix;
        $this->span = $span;
        $this->configuration = $configuration ?? [];
        $this->shownMixinsAndFunctions = $shownMixinsAndFunctions;
        $this->shownVariables = $shownVariables;
        $this->hiddenMixinsAndFunctions = $hiddenMixinsAndFunctions;
        $this->hiddenVariables = $hiddenVariables;
    }

    public function getSpan(): FileSpan
    {
        return $this->span;
    }

    public function getUrl(): UriInterface
    {
        return $this->url;
    }

    public function getUrlSpan(): FileSpan
    {
        return SpanUtil::initialQuoted(SpanUtil::withoutInitialAtRule($this->span));
    }

    public function accept(StatementVisitor $visitor)
    {
        return $visitor->visitForwardRule($this);
    }

    public function __toString(): string
    {
        $buffer = '@forward' . StringExpression::quoteText($this->url->toString());

        if ($this->shownMixinsAndFunctions !== null) {
            \assert($this->shownVariables !== null);
            $buffer .= ' show ';
            $buffer .= $this->memberList($this->shownMixinsAndFunctions, $this->shownVariables);
        } elseif ($this->hiddenMixinsAndFunctions !== null) {
            \assert($this->hiddenVariables !== null);
            $buffer .= ' hide ';
            $buffer .= $this->memberList($this->hiddenMixinsAndFunctions, $this->hiddenVariables);
        }

        if ($this->prefix !== null) {
            $buffer .= " as $this->prefix*";
        }

        if (\count($this->configuration) > 0) {
            $buffer .= \sprintf('with (%s)', implode(', ', $this->configuration));
        }

        $buffer .= ';';

        return $buffer;
    }

    /**
     * Returns a combined list of names of the given members.
     *
     * @param string[] $mixinsAndFunctions
     * @param string[] $variables
     */
    private function memberList(array $mixinsAndFunctions, array $variables): string
    {
        return implode(', ', [
            ...$mixinsAndFunctions,
            ...array_map(fn (string $name) => "$$name", $variables),
        ]);
    }
}
