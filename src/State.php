<?php

namespace VisualCraft\Logmon;

class State implements \Serializable
{
    public $offset;
    public $startSign;
    public $startSignOffset1;
    public $startSignOffset2;
    public $endSign;
    public $endSignOffset1;
    public $endSignOffset2;

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            $this->offset,
            $this->startSign,
            $this->startSignOffset1,
            $this->startSignOffset2,
            $this->endSign,
            $this->endSignOffset1,
            $this->endSignOffset2,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list(
            $this->offset,
            $this->startSign,
            $this->startSignOffset1,
            $this->startSignOffset2,
            $this->endSign,
            $this->endSignOffset1,
            $this->endSignOffset2,
        ) = unserialize($serialized);
    }
}
