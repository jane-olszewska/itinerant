<?php

namespace JaneOlszewska\Tests\Itinerant\Strategy;

use JaneOlszewska\Itinerant\NodeAdapter\SecondElement;
use JaneOlszewska\Itinerant\Strategy\Fail;
use JaneOlszewska\Itinerant\Strategy\Choice;
use PHPUnit\Framework\TestCase;

class ChoiceTest extends TestCase
{
    private $initialInstruction = ['initial'];
    private $followupInstruction = ['followup'];
    private $node;
    private $choice;

    protected function setUp()
    {
        $this->node = new SecondElement([1, [2, 3]]);
        $this->choice = new Choice($this->initialInstruction, $this->followupInstruction);
    }

    public function testExecutesFollowupWhenFirstStrategyFails()
    {
        /** @var \Generator $continuation */
        $continuation = ($this->choice)($this->node);
        $this->assertInstanceOf(\Generator::class, $continuation);

        $result = $continuation->current();
        $this->assertEquals([$this->initialInstruction, $this->node], $result);
        $this->assertTrue($continuation->valid());

        $result = $continuation->send(Fail::fail());
        $this->assertEquals([$this->followupInstruction, $this->node], $result);
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

    public function testSkipsFollowupWhenFirstStrategySucceeds()
    {
        /** @var \Generator $continuation */
        $continuation = ($this->choice)($this->node);
        $this->assertInstanceOf(\Generator::class, $continuation);

        $result = $continuation->current();
        $this->assertEquals([$this->initialInstruction, $this->node], $result);
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
}