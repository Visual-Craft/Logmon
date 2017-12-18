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
     * @param string $logFile
     * @param string $stateFilesDir
     */
    public function __construct($logFile, $stateFilesDir)
    {
        $this->logFile = $logFile;
        $this->stateFilesDir = $stateFilesDir;
    }

    /**
     * @param LineFilterInterface $value
     */
    public function setFilter(LineFilterInterface $value)
    {
        $this->filter = $value;
    }

    /**
     * @param string|null $value
     */
    public function setStateId($value)
    {
        $this->stateId = $value;
    }

    /**
     * @param LineProcessorInterface $lineProcessor
     * @param array $options
     */
    public function process(LineProcessorInterface $lineProcessor, array $options = [])
    {
        $options = array_replace([
            'maxLines' => 100,
            'restart' => false,
            'restartOnWrongSign' => true,
        ], $options);
        $options['maxLines'] = (int) $options['maxLines'];
        $logFileHandle = $this->openLogFile();

        try {
            $stateReaderWriter = $this->createStateReaderWriter();
            $this->doProcess($lineProcessor, $stateReaderWriter, $logFileHandle, $options);
        } finally {
            fclose($logFileHandle);

            if (isset($stateReaderWriter)) {
                $stateReaderWriter->close();
            }
        }
    }

    private function doProcess(
        LineProcessorInterface $lineProcessor,
        StateReaderWriter $stateReaderWriter,
        $logFileHandle,
        array $options
    ) {
        $linesCount = 0;
        $prevState = null;
        $initialOffset = 0;
        $stateManager = $this->createStateManager();

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

        $state = $stateManager->create($logFileHandle, $prevState);
        $stateReaderWriter->write($state);
    }

    public function skip()
    {
        $logFileHandle = $this->openLogFile();
        $stateReaderWriter = $this->createStateReaderWriter();
        fseek($logFileHandle, 0, SEEK_END);
        $state = $this->createStateManager()->create($logFileHandle);
        fclose($logFileHandle);
        $stateReaderWriter->write($state);
        $stateReaderWriter->close();
    }

    public function reset()
    {
        $stateReaderWriter = $this->createStateReaderWriter();
        $stateReaderWriter->remove();
    }

    /**
     * @return resource
     */
    private function openLogFile()
    {
        if (!($handle = fopen($this->logFile, 'rb'))) {
            throw new \RuntimeException("can't open log file '{$this->logFile}'");
        }

        return $handle;
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
        return new StateManager('sha1');
    }
}
