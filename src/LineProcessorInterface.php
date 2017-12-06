<?php

namespace VisualCraft\Logmon;

interface LineProcessorInterface
{
    public function start();
    public function stop();

    /**
     * @param string $line
     */
    public function process($line);
}
