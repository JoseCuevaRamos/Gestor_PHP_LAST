<?php

use Phinx\Migration\CreationInterface;
use Phinx\Util\Util;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractTemplateGenerator implements CreationInterface
{
    /** @var InputInterface */
    private $input;
    /** @var OutputInterface */
    private $output;

    /**
     * {@inheritdoc}
     */
    public function __construct(?InputInterface $input = null, ?OutputInterface $output = null)
    {
        if ($input) {
            $this->setInput($input);
        }
        if ($output) {
            $this->setOutput($output);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postCreate(string $migrationPath, string $className, string $baseClassName): void
    {
        // Do nothing
    }

    /**
     * @return InputInterface
     */
    public function getInput(): InputInterface
    {
        return $this->input;
    }

    /**
     * @param InputInterface $input
     */
    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    /**
     * @return OutputInterface
     */
    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }
}