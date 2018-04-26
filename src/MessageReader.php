<?php

namespace VisualCraft\Logmon;

class MessageReader
{
    /**
     * @var resource
     */
    private $resource;

    /**
     * @var int|null
     */
    private $limit;

    /**
     * @var array
     */
    private $aggregatedMessage;

    /**
     * @var int
     */
    private $messagesCount;

    /**
     * @var bool
     */
    private $closed;

    /**
     * @var int|null
     */
    private $lastMessageOffset;

    /**
     * @var bool
     */
    private $multiLine;

    /**
     * @param resource $resource
     * @param int|null $limit
     * @param bool $multiLine
     */
    public function __construct($resource, $limit = null, $multiLine = true)
    {
        $this->resource = $resource;
        $this->limit = $limit;
        $this->multiLine = $multiLine;
        $this->aggregatedMessage = [];
        $this->messagesCount = 0;
        $this->closed = false;
    }

    /**
     * @return string|null
     */
    public function read()
    {
        if ($this->closed) {
            return null;
        }

        if ($this->multiLine) {
            return $this->readMultiLine();
        }

        return $this->readSingleLine();
    }

    private function readSingleLine()
    {
        if (($line = $this->readLine()) !== false) {
            $this->messagesCount++;

            if ($this->limit !== null && $this->messagesCount >= $this->limit) {
                $this->closed = true;
            }

            return $line;
        }

        return null;
    }

    private function readMultiLine()
    {
        while (($line = $this->readLine()) !== false) {
            if ($this->aggregatedMessage && preg_match('/^\S/', $line)) {
                $this->messagesCount++;
                $message = $this->flush();

                if ($this->limit !== null) {
                    if ($this->messagesCount >= $this->limit) {
                        $this->closed = true;

                        if ($this->lastMessageOffset !== null) {
                            fseek($this->resource, $this->lastMessageOffset);
                        }
                    } else {
                        $lastMessageOffset = ftell($this->resource);

                        if ($lastMessageOffset === false) {
                            throw new \RuntimeException('Unable to get file offset');
                        }

                        $this->lastMessageOffset = $lastMessageOffset;
                    }
                }

                return $message;
            }

            $this->aggregatedMessage[] = $line;
        }

        if ($this->aggregatedMessage) {
            return $this->flush();
        }

        return null;
    }

    /**
     * @return string
     */
    private function flush()
    {
        $message = implode("\n", $this->aggregatedMessage);
        $this->aggregatedMessage = [];

        return $message;
    }

    /**
     * @return string|bool
     */
    private function readLine()
    {
        $line = fgets($this->resource);

        if ($line === false) {
            return false;
        }

        return rtrim($line, "\n");
    }
}
