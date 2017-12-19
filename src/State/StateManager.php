<?php

namespace VisualCraft\Logmon\State;

class StateManager
{
    const DEFAULT_SIGN_BLOCK_SIZE = 256;

    /**
     * @var string
     */
    private $hashAlgo;

    /**
     * @param string $hashAlgo
     */
    public function __construct($hashAlgo)
    {
        $this->hashAlgo = $hashAlgo;
    }

    /**
     * @param resource $handle
     * @return State
     */
    public function create($handle)
    {
        $state = new State();
        $state->offset = ftell($handle);

        $state->startSignOffset1 = 0;
        $state->startSignOffset2 = min(self::DEFAULT_SIGN_BLOCK_SIZE, $state->offset);
        $state->startSign = $this->calculateSign(
            $handle,
            $state->startSignOffset1,
            $state->startSignOffset2
        );

        $state->endSignOffset1 = max(
            $state->startSignOffset2,
            $state->offset - self::DEFAULT_SIGN_BLOCK_SIZE
        );
        $state->endSignOffset2 = $state->offset;
        $state->endSign = $this->calculateSign(
            $handle,
            $state->endSignOffset1,
            $state->endSignOffset2
        );

        return $state;
    }

    /**
     * @param resource $handle
     * @param State $state
     * @return bool
     */
    public function isValid($handle, State $state)
    {
        $realStartSign = $this->calculateSign($handle, $state->startSignOffset1, $state->startSignOffset2);
        $realEndSign = $this->calculateSign($handle, $state->endSignOffset1, $state->endSignOffset2);

        return ($realStartSign === $state->startSign) && ($realEndSign === $state->endSign);
    }

    /**
     * @param resource $handle
     * @param int $offset1
     * @param int $offset2
     * @return string
     */
    private function calculateSign($handle, $offset1, $offset2)
    {
        if ($offset1 >= $offset2) {
            return '';
        }

        fseek($handle, $offset1);
        $content = fread($handle, $offset2 - $offset1);

        if ($content === '') {
            return '';
        }

        return hash($this->hashAlgo, $content);
    }
}
