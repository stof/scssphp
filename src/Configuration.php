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

use JiriPudil\SealedClasses\Sealed;

/**
 * A set of variables meant to configure a module by overriding its
 * `!default` declarations.
 *
 * A configuration may be either *implicit*, meaning that it's either empty or
 * created by importing a file containing a `@forward` rule; or *explicit*,
 * meaning that it's created by passing a `with` clause to a `@use` rule.
 * Explicit configurations have spans associated with them and are represented
 * by the {@see ExplicitConfiguration} subclass.
 *
 * @internal
 */
#[Sealed([ExplicitConfiguration::class])]
class Configuration
{
    /**
     * @var array<string, ConfiguredValue>
     */
    private array $values;

    private readonly ?Configuration $originalConfiguration;

    /**
     * @param array<string, ConfiguredValue> $values
     */
    protected function __construct(array $values, ?Configuration $originalConfiguration)
    {
        $this->values = $values;
        $this->originalConfiguration = $originalConfiguration;
    }

    /**
     * @param array<string, ConfiguredValue> $values
     */
    public static function implicit(array $values): self
    {
        return new self($values, null);
    }

    public static function empty(): self
    {
        return new self([], null);
    }

    /**
     * @return array<string, ConfiguredValue>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    protected function getOriginalConfiguration(): Configuration
    {
        return $this->originalConfiguration ?? $this;
    }

    /**
     * Returns whether `this` and $that {@see Configuration}s have the same
     * {@see getOriginalConfiguration}.
     *
     * An implicit configuration will always return `false` because it was not
     * created through another configuration.
     *
     * {@see ExplicitConfiguration}s and configurations created {@see throughForward}
     * will be considered to have the same original config if they were created
     * as a copy from the same base configuration.
     */
    public function sameOriginal(Configuration $that): bool
    {
        return $this->getOriginalConfiguration() === $that->getOriginalConfiguration();
    }

    public function isEmpty(): bool
    {
        return empty($this->values);
    }

    public function remove(string $name): ?ConfiguredValue
    {
        if ($this->isEmpty()) {
            return null;
        }

        $oldValue = $this->values[$name] ?? null;

        unset($this->values[$name]);

        return $oldValue;
    }

    /**
     * Returns a copy of `this` {@see Configuration} with the given $values map.
     *
     * The copy will have the same {@see getOriginalConfiguration} as `this` config.
     *
     * @param array<string, ConfiguredValue> $values
     */
    protected function withValues(array $values): self
    {
        return new self($values, $this->getOriginalConfiguration());
    }
}
