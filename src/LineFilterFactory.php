<?php

namespace VisualCraft\Logmon;

use VisualCraft\Logmon\LineFilter\ChainLineFilter;
use VisualCraft\Logmon\LineFilter\RegexMatchLineFilter;
use VisualCraft\Logmon\LineFilter\RegexReplaceLineFilter;

class LineFilterFactory
{
    /**
     * @param string|string[] $definitions
     * @return LineFilterInterface
     */
    public function create($definitions)
    {
        $filters = [];

        foreach ((array) $definitions as $definition) {
            $definition = (string) $definition;
            $filter = $this->createSingle($definition);

            if ($filter === null) {
                throw new \RuntimeException(sprintf("Filter definition '%s' is not supported", $definition));
            }

            $filters[] = $filter;
        }

        if (count($filters) === 1) {
            return reset($filters);
        }

        return new ChainLineFilter($filters);
    }

    /**
     * @param string $definition
     * @return LineFilterInterface|null
     */
    private function createSingle($definition)
    {
        if (preg_match('#\A(?<negation>!)?(?<regex>/.+/[a-z]*)\z#ms', $definition, $m)) {
            return new RegexMatchLineFilter($m['regex'], $m['negation'] === '!');
        }

        if (preg_match('#\As/(?<parts>.+)/(?<modifiers>[a-z]*)\z#ms', $definition, $m)) {
            $separatorPosition = null;

            for ($index = 0, $limit = strlen($m['parts']); $index < $limit; $index++) {
                if ($m['parts'][$index] === '/') {
                    for ($escapeIndex = 0; $escapeIndex < $index;) {
                        if ($m['parts'][$index - ($escapeIndex + 1)] === '\\') {
                            $escapeIndex++;
                        } else {
                            break;
                        }
                    }

                    if ($escapeIndex === 0 || $escapeIndex % 2 === 0) {
                        if ($separatorPosition !== null) {
                            throw new \RuntimeException('Invalid regex replace line filter definition: duplicated replace part');
                        }

                        $separatorPosition = $index;
                    }
                }
            }

            if ($separatorPosition === null) {
                throw new \RuntimeException('Invalid regex replace line filter definition: missing replace part');
            }

            $regex = '/' . substr($m['parts'], 0, $separatorPosition) . '/' . $m['modifiers'];
            $replace = substr($m['parts'], $separatorPosition + 1);

            return new RegexReplaceLineFilter($regex, $replace);
        }

        return null;
    }
}
