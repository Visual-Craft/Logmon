<?php

namespace VisualCraft\Logmon\State;

abstract class BaseStateSerializer implements StateSerializerInterface
{
    /**
     * @var array|null
     */
    private static $fields;

    /**
     * @return array
     */
    protected function getFields()
    {
        if (self::$fields === null) {
            try {
                $class = new \ReflectionClass(State::class);
            } catch (\ReflectionException $e) {
                throw new \RuntimeException(sprintf('Unable to build fields list for %s class', State::class));
            }

            foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if (!$property->isStatic()) {
                    $name = $property->getName();
                    self::$fields[$name] = $name;
                }
            }
        }

        return self::$fields;
    }

    /**
     * @param State $state
     * @return array
     */
    protected function normalize(State $state)
    {
        $data = [];

        foreach ($this->getFields() as $objectField => $dataField) {
            $data[$dataField] = $state->{$objectField};
        }

        return $data;
    }

    /**
     * @param array $data
     * @return State
     */
    protected function denormalize(array $data)
    {
        if (array_diff(array_values($this->getFields()), array_keys($data))) {
            throw new \RuntimeException('Malformed state data');
        }

        $state = new State();

        foreach ($this->getFields() as $objectField => $dataField) {
            if (isset($data[$dataField])) {
                $state->{$objectField} = $data[$dataField];
            }
        }

        return $state;
    }
}
