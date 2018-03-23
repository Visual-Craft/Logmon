<?php

namespace VisualCraft\Logmon\MessageWriter;

use VisualCraft\Logmon\Message;

trait FileReportingMessageWriterTrait
{
    private $lastFile;

    private function buildContent(Message $message)
    {
        if ($this->lastFile === null) {
            $prefix = $message->file . ":\n";
        } elseif ($this->lastFile !== $message->file) {
            $prefix = "\n\n" . $message->file . ":\n";
        } else {
            $prefix = '';
        }

        $this->lastFile = $message->file;

        return $prefix . $message->content;
    }
}
