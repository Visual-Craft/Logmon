<?php

namespace VisualCraft\Logmon\LineFilter;

use VisualCraft\Logmon\LineFilterInterface;

class RegexMatchLineFilter implements LineFilterInterface
{
    use RegexValidationTrait;

    /**
     * @var string
     */
    private $regex;

    /**
     * @var bool
     */
    private $negation;

    /**
     * @param string $regex
     * @param bool $negation
     */
    public function __construct($regex, $negation)
    {
        $this->validateRegex($regex);
        $this->regex = $regex;
        $this->negation = $negation;
    }

    /**
     * {@inheritdoc}
     */
    public function filter($line)
    {
        if ((preg_match($this->regex, $line) === 1) === $this->negation) {
            return null;
        }

        return $line;
    }
}
