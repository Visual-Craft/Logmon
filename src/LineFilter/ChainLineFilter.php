<?php

namespace VisualCraft\Logmon\LineFilter;

use VisualCraft\Logmon\LineFilterInterface;

class ChainLineFilter implements LineFilterInterface
{
    /**
     * @var LineFilterInterface[]
     */
    private $filters;

    /**
     * @param LineFilterInterface[] $filters
     */
    public function __construct(array $filters)
    {
        if (!$filters) {
            throw new \InvalidArgumentException("Argument 'filters' should contain at least 1 element.");
        }

        foreach ($filters as $item) {
            if (!$item instanceof LineFilterInterface) {
                throw new \InvalidArgumentException(sprintf(
                    "Argument 'filters' should be array of '%s' instances.",
                    LineFilterInterface::class
                ));
            }
        }

        $this->filters = $filters;
    }

    /**
     * {@inheritdoc}
     */
    public function filter($line)
    {
        $filteredLine = $line;

        foreach ($this->filters as $item) {
            $filteredLine = $item->filter($filteredLine);

            if ($filteredLine === null || $filteredLine === '') {
                return $filteredLine;
            }
        }

        return $filteredLine;
    }
}
