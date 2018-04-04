<?php

namespace JaneOlszewska\Tests\Itinerant\Strategy;

use JaneOlszewska\Itinerant\NodeAdapter\NodeAdapterInterface;
use JaneOlszewska\Itinerant\NodeAdapter\SecondElement;
use JaneOlszewska\Itinerant\Strategy\All;
use JaneOlszewska\Itinerant\Strategy\Fail;
use PHPUnit\Framework\TestCase;

class AllTest extends TestCase
{
    private $childInstruction = ['resolve-child'];

    /** @var NodeAdapterInterface */
    private $node;

    /** @var All */
    private $all;

    protected function setUp()
    {
        $this->node = new SecondElement([1, [2, 3]]);
        $this->all = new All($this->childInstruction);
    }

    public function testReturnsNodeWhenAllChildrenSucceeded()
    {
        $continuation = $this->all->apply($this->node);

        $result = $continuation->current();
        $this->assertEquals($this->childInstruction, $result[0]);
        $this->assertEquals(2, $result[1]->getValue());
        $this->assertTrue($continuation->valid());

        $result = $continuation->send($this->node);
        $this->assertEquals($this->childInstruction, $result[0]);
        $this->assertEquals(3, $result[1]->getValue());
        $this->assertTrue($continuation->valid());

        $result = $continuation->send($this->node);
        $this->assertEquals($this->node, $result);
        $this->assertTrue($continuation->valid());

        // for illustration purposes only:
        // sadly, we can't tell just by calling valid() that we won't get any more values

        $result = $continuation->send(1);
        $this->assertNull($result);
        $this->assertFalse($continuation->valid());
    }

    public function testFailsWhenOneChildFailed()
    {
        $continuation = $this->all->apply($this->node);

        $result = $continuation->current();
        $this->assertEquals($this->childInstruction, $result[0]);
        $this->assertEquals(2, $result[1]->getValue());
        $this->assertTrue($continuation->valid());

        $result = $continuation->send(Fail::fail());
        $this->assertEquals(Fail::fail(), $result);
        $this->assertTrue($continuation->valid());

        // for illustration purposes only:
        // sadly, we can't tell just by calling valid() that we won't get any more values

        $result = $continuation->send(1);
        $this->assertNull($result);
        $this->assertFalse($continuation->valid());
    }

    public function testReturnsNodeIfNoChildren()
    {
        $node = new SecondElement([2]);

        $continuation = $this->all->apply($node);

        $result = $continuation->current();
        $this->assertEquals($node, $result);
    }
}
