<?php

namespace VisualCraft\Logmon\State;

class StateReaderWriter
{
    const MAX_ALLOWED_STATE_FILE_SIZE = 512;

    /**
     * @var string
     */
    private $file;

    /**
     * @var resource
     */
    private $handle;

    /**
     * @var StateSerializerInterface
     */
    private $serializer;

    /**
     * @param StateSerializerInterface $serializer
     * @param string $logFile
     * @param string $dir
     * @param string|null $stateId
     */
    public function __construct(StateSerializerInterface $serializer, $logFile, $dir, $stateId = null)
    {
        if (!is_dir($dir) && !@mkdir($dir) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf("Unable to create '%s' directory.", $dir));
        }

        if (!is_writable($dir)) {
            throw new \RuntimeException(sprintf("'%s' is not writable.", $dir));
        }

        if ($stateId === null) {
            $stateId = 'default';
        }

        $fileNameParts = [
            substr(hash('sha256', dirname($logFile)), 0, 8),
            substr(hash('sha256', basename($logFile)), 0, 8),
            substr(hash('sha256', $stateId), 0, 8),
            hash('sha256', implode("\n", [
                $logFile,
                $stateId,
            ])),
        ];
        $this->file = $dir . '/' . implode('-', $fileNameParts) . '.state';
        touch($this->file);
        $handle = fopen($this->file, 'rb+');

        if (!flock($handle, LOCK_EX|LOCK_NB)) {
            fclose($handle);

            throw new \RuntimeException(sprintf("State file '%s' is locked", $this->file));
        }

        $this->handle = $handle;
        $this->serializer = $serializer;
    }

    /**
     * @return State|null
     */
    public function read()
    {
        fseek($this->handle, 0);
        $content = stream_get_contents($this->handle, self::MAX_ALLOWED_STATE_FILE_SIZE);

        if ($content === false) {
            throw new \RuntimeException(sprintf("Unable to read state file '%s'.", $this->file));
        }

        if ($content === '') {
            return null;
        }

        try {
            $state = $this->serializer->deserialize($content);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf("State file '%s' contains malformed data.", $this->file), 0, $e);
        }

        return $state;
    }

    /**
     * @param State $state
     */
    public function write(State $state)
    {
        ftruncate($this->handle, 0);
        fseek($this->handle, 0);
        fwrite($this->handle, $this->serializer->serialize($state));
        fflush($this->handle);
    }

    public function close()
    {
        if ($this->handle) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function remove()
    {
        $this->close();
        unlink($this->file);
    }
}
