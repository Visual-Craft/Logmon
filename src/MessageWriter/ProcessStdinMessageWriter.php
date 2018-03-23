<?php

namespace VisualCraft\Logmon\MessageWriter;

use VisualCraft\Logmon\Message;
use VisualCraft\Logmon\MessageWriterInterface;

class ProcessStdinMessageWriter implements MessageWriterInterface
{
    use FileReportingMessageWriterTrait;

    /**
     * @var string
     */
    private $commandLine;

    /**
     * @var resource|null
     */
    private $resource;

    /**
     * @param string $commandLine
     */
    public function __construct($commandLine)
    {
        $this->commandLine = $commandLine;
    }

    public function start()
    {
        $resource = popen($this->commandLine, 'w');

        if ($resource === false) {
            throw new \RuntimeException(sprintf("Unable to start process '%s'", $this->commandLine));
        }

        $this->resource = $resource;
    }

    public function stop()
    {
        pclose($this->resource);
        $this->resource = null;
    }

    /**
     * {@inheritdoc}
     */
    public function write(Message $message)
    {
        fwrite($this->resource, $this->buildContent($message));
    }
}
