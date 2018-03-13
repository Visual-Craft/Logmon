<?php

namespace VisualCraft\Logmon\State;

interface StateSerializerInterface
{
    /**
     * @param State $state
     * @return string
     */
    public function serialize(State $state);

    /**
     * @param string $string
     * @return State
     */
    public function deserialize($string);
}
