<?php

namespace VisualCraft\Logmon;

class Message
{
    /**
     * @var string
     */
    public $content;

    /**
     * @var string
     */
    public $file;

    /**
     * @param string $content
     * @param string $file
     */
    public function __construct($content, $file)
    {
        $this->content = $content;
        $this->file = $file;
    }
}
