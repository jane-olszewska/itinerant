<?php

namespace JaneOlszewska\Itinerant\NodeAdapter;

interface NodeAdapterInterface
{
    /**
     * Get node (value+children) in the original shape, children unwrapped.
     *
     * @return mixed
     */
    public function &getNode();

    /**
     * Get node value.
     *
     * @return mixed
     */
    public function getValue();

    /**
     * Get node children, wrapped in adapters.
     *
     * @return NodeAdapterInterface[]
     */
    public function getChildren(): \Iterator;

    /**
     * Set node children, wrapped in adapters.
     *
     * @param NodeAdapterInterface[] $children
     * @return void
     */
    public function setChildren(array $children = []): void;
}