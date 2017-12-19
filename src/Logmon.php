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
     * @param string|null $stateId
     */
    public function __construct($logFile, $stateFilesDir, $stateId = null)
    {
        $this->logFile = $logFile;
        $this->stateFilesDir = $stateFilesDir;
        $this->stateId = $stateId;
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
        $stateReaderWriter = $this->createStateReaderWriter();

        try {
            $logFileHandle = $this->openFile($this->logFile);
            $this->doProcess($lineProcessor, $stateReaderWriter, $logFileHandle, $options);
        } finally {
            if (isset($logFileHandle)) {
                fclose($logFileHandle);
            }

            $stateReaderWriter->close();
        }
    }

    private function doProcess(
        LineProcessorInterface $lineProcessor,
        StateReaderWriter $stateReaderWriter,
        $logFileHandle,
        array $options
    ) {
        $options = array_replace([
            'maxLines' => 100,
            'restart' => false,
            'restartOnWrongSign' => true,
        ], $options);
        $options['maxLines'] = (int) $options['maxLines'];

        $linesCount = 0;
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

        $state = $stateManager->create($logFileHandle);
        $stateReaderWriter->write($state);
    }

    public function skip()
    {
        $stateReaderWriter = $this->createStateReaderWriter();
        $logFileHandle = $this->openFile($this->logFile);
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
     * @param string $file
     * @return resource
     */
    private function openFile($file)
    {
        if (!($handle = fopen($file, 'rb'))) {
            throw new \RuntimeException("can't open file '{$file}'");
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
