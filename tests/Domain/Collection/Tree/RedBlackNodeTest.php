<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Tree;

use Fight\Common\Domain\Collection\Tree\RedBlackNode;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RedBlackNode::class)]
class RedBlackNodeTest extends UnitTestCase
{
    public function test_that_red_constant_is_true(): void
    {
        self::assertTrue(RedBlackNode::RED);
    }

    public function test_that_black_constant_is_false(): void
    {
        self::assertFalse(RedBlackNode::BLACK);
    }

    public function test_that_constructor_sets_key_value_size_and_color(): void
    {
        $node = new RedBlackNode('k', 'v', 3, RedBlackNode::RED);

        self::assertSame('k', $node->key());
        self::assertSame('v', $node->value());
        self::assertSame(3, $node->size());
        self::assertTrue($node->color());
    }

    public function test_that_left_returns_null_by_default(): void
    {
        $node = new RedBlackNode(1, 'a', 1, RedBlackNode::BLACK);

        self::assertNull($node->left());
    }

    public function test_that_right_returns_null_by_default(): void
    {
        $node = new RedBlackNode(1, 'a', 1, RedBlackNode::BLACK);

        self::assertNull($node->right());
    }

    public function test_that_set_left_links_to_the_given_node(): void
    {
        $node = new RedBlackNode(2, 'b', 2, RedBlackNode::BLACK);
        $left = new RedBlackNode(1, 'a', 1, RedBlackNode::RED);

        $node->setLeft($left);

        self::assertSame($left, $node->left());
    }

    public function test_that_set_left_accepts_null_to_unlink(): void
    {
        $node = new RedBlackNode(2, 'b', 2, RedBlackNode::BLACK);
        $node->setLeft(new RedBlackNode(1, 'a', 1, RedBlackNode::RED));

        $node->setLeft(null);

        self::assertNull($node->left());
    }

    public function test_that_set_right_links_to_the_given_node(): void
    {
        $node = new RedBlackNode(1, 'a', 2, RedBlackNode::BLACK);
        $right = new RedBlackNode(2, 'b', 1, RedBlackNode::RED);

        $node->setRight($right);

        self::assertSame($right, $node->right());
    }

    public function test_that_set_right_accepts_null_to_unlink(): void
    {
        $node = new RedBlackNode(1, 'a', 2, RedBlackNode::BLACK);
        $node->setRight(new RedBlackNode(2, 'b', 1, RedBlackNode::RED));

        $node->setRight(null);

        self::assertNull($node->right());
    }

    public function test_that_set_key_updates_the_key(): void
    {
        $node = new RedBlackNode('old', 'v', 1, RedBlackNode::BLACK);

        $node->setKey('new');

        self::assertSame('new', $node->key());
    }

    public function test_that_set_value_updates_the_value(): void
    {
        $node = new RedBlackNode('k', 'old', 1, RedBlackNode::BLACK);

        $node->setValue('new');

        self::assertSame('new', $node->value());
    }

    public function test_that_set_size_updates_the_size(): void
    {
        $node = new RedBlackNode('k', 'v', 1, RedBlackNode::BLACK);

        $node->setSize(5);

        self::assertSame(5, $node->size());
    }

    public function test_that_set_color_updates_the_color(): void
    {
        $node = new RedBlackNode('k', 'v', 1, RedBlackNode::BLACK);

        $node->setColor(RedBlackNode::RED);

        self::assertTrue($node->color());
    }

    public function test_that_clone_produces_independent_copies_of_children(): void
    {
        $root = new RedBlackNode(2, 'b', 3, RedBlackNode::BLACK);
        $left = new RedBlackNode(1, 'a', 1, RedBlackNode::RED);
        $right = new RedBlackNode(3, 'c', 1, RedBlackNode::RED);
        $root->setLeft($left);
        $root->setRight($right);

        $copy = clone $root;
        $copy->left()->setValue('mutated');

        self::assertSame('a', $root->left()->value());
        self::assertSame('mutated', $copy->left()->value());
    }

    public function test_that_clone_with_no_children_does_not_error(): void
    {
        $node = new RedBlackNode('k', 'v', 1, RedBlackNode::BLACK);

        $copy = clone $node;

        self::assertNull($copy->left());
        self::assertNull($copy->right());
    }
}
