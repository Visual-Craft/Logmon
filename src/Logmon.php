<?php

namespace VisualCraft\Logmon;

use VisualCraft\Logmon\State\StateManager;
use VisualCraft\Logmon\State\StateReaderWriter;

class Logmon
{
    const DEFAULT_SIGN_BLOCK_SIZE = 256;

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

        if (!$options['restart'] && ($prevState = $stateReaderWriter->read()) !== null) {
            if ($this->checkSign($logFileHandle, $prevState)) {
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

        $state = $this->createState($logFileHandle, $prevState);
        $stateReaderWriter->write($state);
    }

    public function skip()
    {
        $logFileHandle = $this->openLogFile();
        $stateReaderWriter = $this->createStateReaderWriter();
        fseek($logFileHandle, 0, SEEK_END);
        $state = $this->createState($logFileHandle);
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
     * @param resource $handle
     * @param State|null $prevState
     * @return State
     */
    private function createState($handle, State $prevState = null)
    {
        $state = new State();
        $state->offset = ftell($handle);

        $state->startSignOffset1 = 0;
        $state->startSignOffset2 = min(self::DEFAULT_SIGN_BLOCK_SIZE, $state->offset);

        if (
            $prevState !== null
                &&
            $state->startSignOffset1 === $prevState->startSignOffset1
                &&
            $state->startSignOffset2 === $prevState->startSignOffset2
        ) {
            $state->startSign = $prevState->startSign;
        } else {
            $state->startSign = $this->calculateSign(
                $handle,
                $state->startSignOffset1,
                $state->startSignOffset2
            );
        }

        $state->endSignOffset1 = max(
            $state->startSignOffset2,
            $state->offset - self::DEFAULT_SIGN_BLOCK_SIZE
        );
        $state->endSignOffset2 = $state->offset;
        $state->endSign = $this->calculateSign(
            $handle,
            $state->endSignOffset1,
            $state->endSignOffset2
        );

        return $state;
    }

    /**
     * @param resource $handle
     * @param int $offset1
     * @param int $offset2
     * @return string
     */
    private function calculateSign($handle, $offset1, $offset2)
    {
        if ($offset1 >= $offset2) {
            return '';
        }

        fseek($handle, $offset1);
        $content = fread($handle, $offset2 - $offset1);

        if ($content === '') {
            return '';
        }

        return hash('sha1', $content);
    }

    /**
     * @param resource $handle
     * @param State $state
     * @return bool
     */
    private function checkSign($handle, State $state)
    {
        $realStartSign = $this->calculateSign($handle, $state->startSignOffset1, $state->startSignOffset2);
        $realEndSign = $this->calculateSign($handle, $state->endSignOffset1, $state->endSignOffset2);

        return ($realStartSign === $state->startSign) && ($realEndSign === $state->endSign);
    }
}
