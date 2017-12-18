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
     * @param string $logFile
     * @param string $dir
     * @param string|null $stateId
     */
    public function __construct($logFile, $dir, $stateId)
    {
        if (!is_dir($dir) && !@mkdir($dir) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf("Unable to create '%s' directory.", $dir));
        }

        if (!is_writable($dir)) {
            throw new \RuntimeException(sprintf("'%s' is not writable.", $dir));
        }

        if ($stateId !== null && !preg_match('/^[\w\-]+$/', $stateId)) {
            throw new \InvalidArgumentException("State id should contain only letters, digits, '_' and '-'.");
        }

        $fileNameParts = [
            hash('sha1', dirname($logFile)),
            basename($logFile),
        ];

        if ($stateId !== null) {
            $fileNameParts[] = $stateId;
        }

        $this->file = $dir . '/' . implode('-', $fileNameParts) . '.state';
        touch($this->file);
        $handle = fopen($this->file, 'rb+');

        if (!flock($this->handle, LOCK_EX|LOCK_NB)) {
            fclose($handle);

            throw new \RuntimeException(sprintf("State file '%s' is locked", $this->file));
        }

        $this->handle = $handle;
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

        $state = @unserialize($content);

        if (!$state instanceof State) {
            throw new \RuntimeException(sprintf("State file '%s' contains malformed data.", $this->file));
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
        fwrite($this->handle, serialize($state));
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
