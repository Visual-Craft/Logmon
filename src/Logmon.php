<?php

namespace VisualCraft\Logmon;

use VisualCraft\Logmon\Input\Input;
use VisualCraft\Logmon\State\JsonStateSerializer;
use VisualCraft\Logmon\State\StateManager;
use VisualCraft\Logmon\State\StateReaderWriter;

class Logmon
{
    /**
     * @var string
     */
    private $stateFilesDir;

    /**
     * @var string|null
     */
    private $stateId;

    /**
     * @var resource[]
     */
    private $handles;

    /**
     * @param string $stateFilesDir
     * @param string|null $stateId
     */
    public function __construct($stateFilesDir, $stateId = null)
    {
        $this->stateFilesDir = $stateFilesDir;
        $this->stateId = $stateId;
        $this->handles = [];
    }

    /**
     * @param Input $input
     * @param MessageWriterInterface $messageWriter
     * @param array $options
     */
    public function process(Input $input, MessageWriterInterface $messageWriter, array $options = [])
    {
        $options = array_replace([
            'maxMessagesPerInput' => 0,
            'restart' => false,
            'restartOnWrongSign' => true,
            'filter' => null,
            'noMultiline' => false,
        ], $options);
        $options['maxMessagesPerInput'] = (int) $options['maxMessagesPerInput'];

        if ($options['filter'] && !$options['filter'] instanceof LineFilterInterface) {
            throw new \InvalidArgumentException(sprintf("Value of options['filter'] should be instance of %s or null", LineFilterInterface::class));
        }

        $writerStarted = false;

        foreach ($input->getItems() as $item) {
            $this->openStateAndRun($item->getPath(), function ($path, StateReaderWriter $stateReaderWriter) use (&$writerStarted, $messageWriter, $options) {
                $initialOffset = 0;
                $stateManager = $this->createStateManager();
                $logFileHandle = $this->openFile($path);

                if (!$options['restart'] && ($prevState = $stateReaderWriter->read()) !== null) {
                    if ($stateManager->isValid($logFileHandle, $prevState)) {
                        $initialOffset = $prevState->offset;
                    } elseif (!$options['restartOnWrongSign']) {
                        throw new \RuntimeException('invalid log file sign');
                    }
                }

                fseek($logFileHandle, $initialOffset);
                $messageReader = new MessageReader(
                    $logFileHandle,
                    $options['maxMessagesPerInput'] > 0 ? $options['maxMessagesPerInput'] : null,
                    !$options['noMultiline']
                );

                while (($message = $messageReader->read()) !== null) {
                    if ($options['filter']) {
                        $filteredMessage = $options['filter']->filter($message);

                        if ($filteredMessage === null || $filteredMessage === '') {
                            continue;
                        }

                        $message = $filteredMessage;
                    }

                    if (!$writerStarted) {
                        $messageWriter->start();
                    }

                    $messageWriter->write(new Message($message, $path));
                }

                $state = $stateManager->create($logFileHandle);
                $stateReaderWriter->write($state);
            });
        }

        if ($writerStarted) {
            $messageWriter->stop();
        }
    }

    public function skip(Input $input)
    {
        foreach ($input->getItems() as $item) {
            $this->openStateAndRun($item->getPath(), function ($path, StateReaderWriter $stateReaderWriter) {
                $logFileHandle = $this->openFile($path);
                fseek($logFileHandle, 0, SEEK_END);
                $state = $this->createStateManager()->create($logFileHandle);
                $stateReaderWriter->write($state);
            });
        }
    }

    public function reset(Input $input)
    {
        foreach ($input->getItems() as $item) {
            $this->openStateAndRun($item->getPath(), function ($path, StateReaderWriter $stateReaderWriter) {
                $stateReaderWriter->remove();
            });
        }
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
     * @param string $path
     * @return StateReaderWriter
     */
    private function createStateReaderWriter($path)
    {
        return new StateReaderWriter(new JsonStateSerializer(), $path, $this->stateFilesDir, $this->stateId);
    }

    /**
     * @return StateManager
     */
    private function createStateManager()
    {
        return new StateManager('sha256');
    }

    /**
     * @param string $path
     * @param callable $callable
     */
    private function openStateAndRun($path, callable $callable)
    {
        $stateReaderWriter = $this->createStateReaderWriter($path);

        try {
            $callable($path, $stateReaderWriter);
        } finally {
            $this->closeHandles();
            $stateReaderWriter->close();
        }
    }
}
