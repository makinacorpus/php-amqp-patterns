<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns\Sample;

use MakinaCorpus\AMQP\Patterns\PatternFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
        $this->addOption('exchange', null, InputOption::VALUE_OPTIONAL, "Exchange on which to connect", $this->exchange);
        $this->addOption('routing-key', null, InputOption::VALUE_NONE, "Routing key to use to publish message", null);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$exchange = $input->getOption('exchange')) {
            $exchange = $this->exchange;
        }

        $output->writeln("<comment>Using '{$exchange}' exchange.</comment>");

        if ($routingKey = $input->getOption('routing-key')) {
            $output->writeln("<comment>Using '{$routingKey}' routing_key.</comment>");
        } else {
            $output->writeln("<comment>Using no routing_key.</comment>");
        }

        $this
            ->factory
            ->createFanoutPublisher($exchange)
            ->publish($input->getArgument('message'), [], $routingKey)
        ;
    }
}
