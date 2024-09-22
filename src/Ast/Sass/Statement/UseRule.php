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
use ScssPhp\ScssPhp\Util\ListUtil;
use ScssPhp\ScssPhp\Util\SpanUtil;
use ScssPhp\ScssPhp\Visitor\StatementVisitor;
use SourceSpan\FileSpan;

/**
 * A `@use` rule.
 *
 * @internal
 */
final class UseRule implements Statement, SassDependency
{
    /**
     * The URI of the module to use.
     *
     * If this is relative, it's relative to the containing file.
     */
    private readonly UriInterface $url;

    /**
     * The namespace for members of the used module, or `null` if the members
     * can be accessed without a namespace.
     */
    public readonly ?string $namespace;

    /**
     * A list of variable assignments used to configure the loaded modules.
     *
     * @var list<ConfiguredVariable>
     */
    public readonly array $configuration;

    private readonly FileSpan $span;

    /**
     * @param list<ConfiguredVariable>|null $configuration
     */
    public function __construct(UriInterface $url, ?string $namespace, FileSpan $span, ?array $configuration = null)
    {
        $this->url = $url;
        $this->namespace = $namespace;
        $this->span = $span;
        $this->configuration = $configuration ?? [];

        foreach ($this->configuration as $variable) {
            if ($variable->isGuarded()) {
                throw new \InvalidArgumentException('configured variable can\'t be guarded in a @use rule.');
            }
        }
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
        return $visitor->visitUseRule($this);
    }

    public function __toString(): string
    {
        $buffer = '@use ' . StringExpression::quoteText($this->url->toString());

        $pathSegments = explode('/', $this->url->getPath());
        $basename = ListUtil::last($pathSegments);
        $dot = strpos($basename, '.');

        if ($this->namespace !== substr($basename, 0, $dot === false ? null : $dot)) {
            $buffer .= ' as ' . ($this->namespace ?? '*');
        }

        if (\count($this->configuration) > 0) {
            $buffer .= \sprintf(" with (%s)", implode(', ', $this->configuration));
        }

        $buffer .= ';';

        return $buffer;
    }
}
