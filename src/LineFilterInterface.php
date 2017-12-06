<?php

namespace VisualCraft\Logmon;

interface LineFilterInterface
{
    /**
     * @param string $line
     * @return string|null
     */
    public function filter($line);
}
