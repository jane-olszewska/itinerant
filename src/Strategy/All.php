<?php

namespace JaneOlszewska\Itinerant\Strategy;

use JaneOlszewska\Itinerant\NodeAdapter\NodeAdapterInterface;

class All
{
    private $firstPhase = true;

    /**
     * @var NodeAdapterInterface
     */
    private $node;

    /**
     * @var NodeAdapterInterface[]
     */
    private $unprocessed;

    /**
     * @var NodeAdapterInterface[]
     */
    private $processed;

    private $childStrategy;

    public function __construct(
        $childStrategy,
        NodeAdapterInterface $node = null
    ) {
        $this->childStrategy = $childStrategy;

        if ($node) {
            $this->node = $node;
        }
    }

    public function __invoke(NodeAdapterInterface $previousResult)
    {
        $result = $this->firstPhase
            ? $this->all($previousResult)
            : $this->allIntermediate($previousResult);

        return $result;
    }

    private function all(NodeAdapterInterface $node)
    {
        if (!$this->node) {
            $this->node = $node;
        }

        // if $d has no children: return $d, strategy terminal independent of what $s1 actually is
        $res = $this->node;

        $unprocessed = $this->node->getChildren();
        $this->unprocessed = iterator_to_array($unprocessed);
        $this->processed = [];

        if ($this->unprocessed) {
            $this->firstPhase = false;
            $child = array_shift($this->unprocessed);

            return [
                [$this, null],
                [$this->childStrategy, $child]
            ];
        }

        return $res;
    }

    private function allIntermediate(NodeAdapterInterface $previousResult)
    {
        $res = $previousResult;

        // if the result of the last child resolution wasn't fail, continue
        if (Fail::fail() !== $previousResult) {
            $this->processed[] = $previousResult;

            if ($this->unprocessed) { // there's more to process
                $child = array_shift($this->unprocessed);

                return [
                    [$this, null],
                    [$this->childStrategy, $child]
                ];
            } else {
                $this->node->setChildren($this->processed);
                $res = $this->node;
            }
        }

        return $res;
    }
}
