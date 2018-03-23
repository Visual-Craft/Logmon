<?php

namespace VisualCraft\Logmon\Input;

class Input
{
    /**
     * @var InputItem[]
     */
    private $items;

    public function __construct()
    {
        $this->items = [];
    }

    /**
     * @param InputItem $item
     */
    public function addItem(InputItem $item)
    {
        $this->items[] = $item;
    }

    /**
     * @return InputItem[]
     */
    public function getItems()
    {
        return $this->items;
    }
}
