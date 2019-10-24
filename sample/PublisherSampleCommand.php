<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns\Sample;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MakinaCorpus\AMQP\Patterns\PatternFactory;

/**
 * Messages consumer
 */
final class PublisherSampleCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'amqp-pattern:sample:publisher';

    /** @var PatternFactory */
    private $factory;

    /** @var string */
    private $exchange;

    /**
     * Default constructor
     */
    public function __construct(PatternFactory $factory, string $exchange)
    {
        parent::__construct();

        $this->factory = $factory;
        $this->exchange = $exchange;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addArgument('message', InputArgument::REQUIRED);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("<comment>Using '{$this->exchange}' exchange.</comment>");

        $this
            ->factory
            ->createPublisher($this->exchange)
            ->publish($input->getArgument('message'))
        ;
    }
}
