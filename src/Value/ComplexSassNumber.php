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

namespace ScssPhp\ScssPhp\Value;

/**
 * A specialized subclass of {@see SassNumber} for numbers that are neither {@see UnitlessSassNumber} nor {@see SingleUnitSassNumber}.
 *
 * @internal
 */
final class ComplexSassNumber extends SassNumber
{
    /**
     * @var string[]
     * @phpstan-var list<string>
     */
    private $numeratorUnits;

    /**
     * @var string[]
     * @phpstan-var list<string>
     */
    private $denominatorUnits;

    /**
     * @param int|float  $value
     * @param string[]   $numeratorUnits
     * @param string[]   $denominatorUnits
     * @param array|null $asSlash
     *
     * @phpstan-param list<string> $numeratorUnits
     * @phpstan-param list<string> $denominatorUnits
     * @phpstan-param array{0: SassNumber, 1: SassNumber}|null $asSlash
     */
    public function __construct($value, array $numeratorUnits, array $denominatorUnits, array $asSlash = null)
    {
        assert(\count($numeratorUnits) > 1 || \count($denominatorUnits) > 0);

        parent::__construct($value, $asSlash);
        $this->numeratorUnits = $numeratorUnits;
        $this->denominatorUnits = $denominatorUnits;
    }

    public function getNumeratorUnits(): array
    {
        return $this->numeratorUnits;
    }

    public function getDenominatorUnits(): array
    {
        return $this->denominatorUnits;
    }

    protected function withValue($value): SassNumber
    {
        return new self($value, $this->numeratorUnits, $this->denominatorUnits);
    }

    public function withSlash(SassNumber $numerator, SassNumber $denominator): SassNumber
    {
        return new self($this->getValue(), $this->numeratorUnits, $this->denominatorUnits, array($numerator, $denominator));
    }

    public function hasUnits(): bool
    {
        return true;
    }

    public function hasUnit(string $unit): bool
    {
        return false;
    }

    public function compatibleWithUnit(string $unit): bool
    {
        return false;
    }
}
