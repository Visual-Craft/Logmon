<?php

namespace VisualCraft\Logmon\State;

class JsonStateSerializer extends BaseStateSerializer
{
    /**
     * {@inheritdoc}
     */
    public function serialize(State $state)
    {
        return json_encode($this->normalize($state));
    }

    /**
     * {@inheritdoc}
     */
    public function deserialize($string)
    {
        $data = @json_decode($string, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Malformed state data');
        }

        return $this->denormalize($data);
    }
}
