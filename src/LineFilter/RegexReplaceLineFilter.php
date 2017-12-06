<?php

namespace VisualCraft\Logmon\LineFilter;

use VisualCraft\Logmon\LineFilterInterface;

class RegexReplaceLineFilter implements LineFilterInterface
{
    use RegexValidationTrait;

    /**
     * @var string
     */
    private $regex;

    /**
     * @var string
     */
    private $replace;

    /**
     * @param string $regex
     * @param string $replace
     */
    public function __construct($regex, $replace)
    {
        $this->validateRegex($regex);
        $this->regex = $regex;
        $this->replace = $replace;
    }

    /**
     * {@inheritdoc}
     */
    public function filter($line)
    {
        return preg_replace($this->regex, $this->replace, $line);
    }
}
