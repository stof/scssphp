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

use ScssPhp\ScssPhp\Ast\Css\CssAtRule;
use ScssPhp\ScssPhp\Ast\Css\CssComment;
use ScssPhp\ScssPhp\Ast\Css\CssDeclaration;
use ScssPhp\ScssPhp\Ast\Css\CssImport;
use ScssPhp\ScssPhp\Ast\Css\CssKeyframeBlock;
use ScssPhp\ScssPhp\Ast\Css\CssMediaRule;
use ScssPhp\ScssPhp\Ast\Css\CssParentNode;
use ScssPhp\ScssPhp\Ast\Css\CssStyleRule;
use ScssPhp\ScssPhp\Ast\Css\CssStylesheet;
use ScssPhp\ScssPhp\Ast\Css\CssSupportsRule;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssAtRule;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssComment;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssDeclaration;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssImport;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssKeyframeBlock;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssMediaRule;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssNode;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssParentNode;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssStyleRule;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssStylesheet;
use ScssPhp\ScssPhp\Ast\Css\ModifiableCssSupportsRule;
use ScssPhp\ScssPhp\Ast\Selector\SelectorList;
use ScssPhp\ScssPhp\Util\Box;
use ScssPhp\ScssPhp\Visitor\CssVisitor;

/**
 * A visitor that creates a deep (and mutable) copy of a {@see CssStylesheet}.
 *
 * @template-implements CssVisitor<ModifiableCssNode>
 *
 * @internal
 */
final class CloneCssVisitor implements CssVisitor
{
    /**
     * A map from selectors in the original stylesheet to selectors generated for
     * the new stylesheet using {@see ExtensionStore::clone()}.
     *
     * @var \SplObjectStorage<SelectorList, Box<SelectorList>>
     */
    private readonly \SplObjectStorage $oldToNewSelectors;

    /**
     * @param \SplObjectStorage<SelectorList, Box<SelectorList>> $oldToNewSelectors
     */
    public function __construct(\SplObjectStorage $oldToNewSelectors)
    {
        $this->oldToNewSelectors = $oldToNewSelectors;
    }

    public function visitCssAtRule(CssAtRule $node): ModifiableCssAtRule
    {
        $rule = new ModifiableCssAtRule($node->getName(), $node->getSpan(), $node->isChildless(), $node->getValue());

        return $node->isChildless() ? $rule : $this->visitChildren($rule, $node);
    }

    public function visitCssComment(CssComment $node): ModifiableCssComment
    {
        return new ModifiableCssComment($node->getText(), $node->getSpan());
    }

    public function visitCssDeclaration(CssDeclaration $node): ModifiableCssDeclaration
    {
        return new ModifiableCssDeclaration($node->getName(), $node->getValue(), $node->getSpan(), $node->isParsedAsCustomProperty(), valueSpanForMap: $node->getValueSpanForMap());
    }

    public function visitCssImport(CssImport $node): ModifiableCssImport
    {
        return new ModifiableCssImport($node->getUrl(), $node->getSpan(), $node->getModifiers());
    }

    public function visitCssKeyframeBlock(CssKeyframeBlock $node): ModifiableCssKeyframeBlock
    {
        return $this->visitChildren(new ModifiableCssKeyframeBlock($node->getSelector(), $node->getSpan()), $node);
    }

    public function visitCssMediaRule(CssMediaRule $node): ModifiableCssMediaRule
    {
        return $this->visitChildren(new ModifiableCssMediaRule($node->getQueries(), $node->getSpan()), $node);
    }

    public function visitCssStyleRule(CssStyleRule $node): ModifiableCssStyleRule
    {
        $newSelector = $this->oldToNewSelectors[$node->getSelector()] ?? null;
        if ($newSelector === null) {
            throw new \LogicException('The ExtensionStore and CssStylesheet passed to cloneCssStylesheet() must come from the same compilation.');
        }

        return $this->visitChildren(new ModifiableCssStyleRule($newSelector, $node->getSpan(), $node->getOriginalSelector()), $node);
    }

    public function visitCssStylesheet(CssStylesheet $node): ModifiableCssStylesheet
    {
        return $this->visitChildren(new ModifiableCssStylesheet($node->getSpan()), $node);
    }

    public function visitCssSupportsRule(CssSupportsRule $node): ModifiableCssSupportsRule
    {
        return $this->visitChildren(new ModifiableCssSupportsRule($node->getCondition(), $node->getSpan()), $node);
    }

    /**
     * Visits $oldParent's children and adds their cloned values as children of
     * $newParent, then returns $newParent.
     *
     * @template T of ModifiableCssParentNode
     *
     * @param T $newParent
     *
     * @return T
     */
    private function visitChildren(ModifiableCssParentNode $newParent, CssParentNode $oldParent): ModifiableCssParentNode
    {
        foreach ($oldParent->getChildren() as $child) {
            $newChild = $child->accept($this);
            $newChild->setGroupEnd($child->isGroupEnd());
            $newParent->addChild($newChild);
        }

        return $newParent;
    }
}
