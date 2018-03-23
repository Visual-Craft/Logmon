<?php

namespace VisualCraft\Logmon;

interface MessageWriterInterface
{
    public function start();
    public function stop();

    /**
     * @param Message $message
     */
    public function write(Message $message);
}
