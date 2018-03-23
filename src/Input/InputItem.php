<?php

namespace VisualCraft\Logmon\Input;

class InputItem
{
    /**
     * @var string
     */
    private $path;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $realPath = realpath($path);

        if ($realPath === false) {
            throw new \InvalidArgumentException(sprintf("Missing input file '%s'", $path));
        }

        $this->path = $realPath;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }
}
