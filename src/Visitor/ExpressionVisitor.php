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

namespace ScssPhp\ScssPhp\Visitor;

use ScssPhp\ScssPhp\Ast\Sass\Expression\BinaryOperationExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\BooleanExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\ColorExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\FunctionExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\IfExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\ListExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\MapExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\NullExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\NumberExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\ParenthesizedExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\SelectorExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\StringExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\UnaryOperationExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\ValueExpression;
use ScssPhp\ScssPhp\Ast\Sass\Expression\VariableExpression;

/**
 * An interface for visitors that traverse SassScript expressions.
 *
 * @internal
 *
 * @phpstan-template T
 */
interface ExpressionVisitor
{
    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitBinaryOperationExpression(BinaryOperationExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitBooleanExpression(BooleanExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitColorExpression(ColorExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitFunctionExpression(FunctionExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitIfExpression(IfExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitListExpression(ListExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitMapExpression(MapExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitNullExpression(NullExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitNumberExpression(NumberExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitParenthesizedExpression(ParenthesizedExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitSelectorExpression(SelectorExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitStringExpression(StringExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitUnaryOperationExpression(UnaryOperationExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitValueExpression(ValueExpression $node);

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function visitVariableExpression(VariableExpression $node);
}