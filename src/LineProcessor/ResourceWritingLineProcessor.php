<?php

namespace VisualCraft\Logmon\LineProcessor;

use VisualCraft\Logmon\LineProcessorInterface;

class ResourceWritingLineProcessor implements LineProcessorInterface
{
    /**
     * @var resource
     */
    private $resource;

    /**
     * @param resource $resource
     */
    public function __construct($resource)
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException(sprintf(
                "Argument 'resource' should be of 'resource' type, but '%s' type is given",
                is_object($resource) ? get_class($resource) : gettype($resource)
            ));
        }

        $this->resource = $resource;
    }

    public function start()
    {
        // does nothing
    }

    public function stop()
    {
        // does nothing
    }

    /**
     * {@inheritdoc}
     */
    public function process($line)
    {
        fwrite($this->resource, $line);
    }
}
