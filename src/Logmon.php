<?php

namespace VisualCraft\Logmon;

use VisualCraft\Logmon\State\StateManager;
use VisualCraft\Logmon\State\StateReaderWriter;

class Logmon
{
    /**
     * @var string
     */
    private $logFile;

    /**
     * @var string
     */
    private $stateFilesDir;

    /**
     * @var LineFilterInterface|null
     */
    private $filter;

    /**
     * @var string|null
     */
    private $stateId;

    /**
     * @var resource[]
     */
    private $handles;

    /**
     * @param string $logFile
     * @param string $stateFilesDir
     * @param string|null $stateId
     */
    public function __construct($logFile, $stateFilesDir, $stateId = null)
    {
        $logFileRealPath = realpath($logFile);

        if ($logFileRealPath === false) {
            throw new \InvalidArgumentException("Argument 'logFile' should be the path to existing file");
        }

        $this->logFile = $logFileRealPath;
        $this->stateFilesDir = $stateFilesDir;
        $this->stateId = $stateId;
        $this->handles = [];
    }

    /**
     * @param LineFilterInterface $value
     */
    public function setFilter(LineFilterInterface $value)
    {
        $this->filter = $value;
    }

    /**
     * @param LineProcessorInterface $lineProcessor
     * @param array $options
     */
    public function process(LineProcessorInterface $lineProcessor, array $options = [])
    {
        $this->openStateAndRun(function (StateReaderWriter $stateReaderWriter) use ($lineProcessor, $options) {
            $options = array_replace([
                'maxLines' => 100,
                'restart' => false,
                'restartOnWrongSign' => true,
            ], $options);
            $options['maxLines'] = (int) $options['maxLines'];

            $linesCount = 0;
            $initialOffset = 0;
            $stateManager = $this->createStateManager();
            $logFileHandle = $this->openFile($this->logFile);

            if (!$options['restart'] && ($prevState = $stateReaderWriter->read()) !== null) {
                if ($stateManager->isValid($logFileHandle, $prevState)) {
                    $initialOffset = $prevState->offset;
                } elseif (!$options['restartOnWrongSign']) {
                    throw new \RuntimeException('invalid log file sign');
                }
            }

            fseek($logFileHandle, $initialOffset);
            $lineProcessorStarted = false;

            while ($line = fgets($logFileHandle)) {
                if ($this->filter) {
                    $filteredLine = $this->filter->filter($line);

                    if ($filteredLine === null || $filteredLine === '') {
                        continue;
                    }

                    $line = $filteredLine;
                }

                $linesCount++;

                if (!$lineProcessorStarted) {
                    $lineProcessor->start();
                    $lineProcessorStarted = true;
                }

                $lineProcessor->process($line);

                if ($options['maxLines'] > 0 && $linesCount >= $options['maxLines']) {
                    break;
                }
            }

            if ($lineProcessorStarted) {
                $lineProcessor->stop();
            }

            $state = $stateManager->create($logFileHandle);
            $stateReaderWriter->write($state);
        });
    }

    public function skip()
    {
        $this->openStateAndRun(function (StateReaderWriter $stateReaderWriter) {
            $logFileHandle = $this->openFile($this->logFile);
            fseek($logFileHandle, 0, SEEK_END);
            $state = $this->createStateManager()->create($logFileHandle);
            $stateReaderWriter->write($state);
        });
    }

    public function reset()
    {
        $this->openStateAndRun(function (StateReaderWriter $stateReaderWriter) {
            $stateReaderWriter->remove();
        });
    }

    /**
     * @param string $file
     * @return resource
     */
    private function openFile($file)
    {
        if (!($handle = fopen($file, 'rb'))) {
            throw new \RuntimeException("can't open file '{$file}'");
        }

        $this->handles[] = $handle;

        return $handle;
    }

    private function closeHandles()
    {
        foreach ($this->handles as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $this->handles = [];
    }

    /**
     * @return StateReaderWriter
     */
    private function createStateReaderWriter()
    {
        return new StateReaderWriter($this->logFile, $this->stateFilesDir, $this->stateId);
    }

    /**
     * @return StateManager
     */
    private function createStateManager()
    {
        return new StateManager('sha256');
    }

    /**
     * @param callable $callable
     */
    private function openStateAndRun(callable $callable)
    {
        $stateReaderWriter = $this->createStateReaderWriter();

        try {
            $callable($stateReaderWriter);
        } finally {
            $this->closeHandles();
            $stateReaderWriter->close();
        }
    }
}
