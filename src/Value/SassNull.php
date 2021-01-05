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

final class SassNull extends Value
{
    /**
     * @var SassNull|null
     */
    private static $instance;

    public static function create(): SassNull
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    public function isTruthy(): bool
    {
        return false;
    }

    public function isBlank(): bool
    {
        return true;
    }

    public function toCssString(): string
    {
        return '';
    }

    public function __toString(): string
    {
        return 'null';
    }

    public function equals(Value $other): bool
    {
        return $other instanceof SassNull;
    }
}
