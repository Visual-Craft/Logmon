<?php

namespace VisualCraft\Logmon\LineProcessor;

use VisualCraft\Logmon\LineProcessorInterface;

class ProcessStdinWritingLineProcessor implements LineProcessorInterface
{
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
    public function process($line)
    {
        fwrite($this->resource, $line);
    }
}
