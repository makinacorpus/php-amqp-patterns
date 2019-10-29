<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns\Sample;

use MakinaCorpus\AMQP\Patterns\PatternFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Messages consumer
 */
final class TaskPublisherSampleCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'amqp-pattern:sample:task-publisher';

    /** @var PatternFactory */
    private $factory;

    /**
     * Default constructor
     */
    public function __construct(PatternFactory $factory)
    {
        parent::__construct();

        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addArgument('message', InputArgument::REQUIRED);
        $this->addOption('content-type', null, InputOption::VALUE_REQUIRED, "Message content type", 'text/plain');
        $this->addOption('exchange', null, InputOption::VALUE_OPTIONAL, "Exchange on which to connect", "");
        $this->addOption('routing-key', null, InputOption::VALUE_REQUIRED, "Routing key to use to publish message", "my_task_queue");
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, "Message type", 'sample_text');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $exchange = $input->getOption('exchange');
        $output->writeln("<comment>Using '{$exchange}' exchange.</comment>");

        $routingKey = $input->getOption('routing-key');
        $output->writeln("<comment>I am sending with the '{$routingKey}' routing key.</comment>");

        $this
            ->factory
            ->createTaskPublisher($routingKey, $exchange)
            ->publish($input->getArgument('message'), [
                'app_id' => 'makinacorpus/amqp-patterns',
                'content_type' => $input->getOption('content-type'),
                'message_id' => (string)Uuid::uuid4(),
                'timestamp' => (new \DateTimeImmutable())->getTimestamp(),
                'type' => $input->getOption('type'),
            ])
        ;
    }
}
