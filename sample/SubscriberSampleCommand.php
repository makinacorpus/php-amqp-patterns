<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns\Sample;

use MakinaCorpus\AMQP\Patterns\PatternFactory;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Messages consumer
 */
final class SubscriberSampleCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'amqp-pattern:sample:subscriber';

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
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("<comment>Using '{$this->exchange}' exchange.</comment>");

        $this
            ->factory
            ->createSubscriber($this->exchange)
            ->callback(function (AMQPMessage $message) use ($output) {
                $output->writeln(\sprintf("[%s] %s", (new \DateTime())->format('Y-m-d H:i:s'), $message->body));
            })
            ->registerSignalHandlers(static function () use ($output) {
                $output->writeln('<comment>... process killed</comment>');
            })
            ->run()
        ;
    }
}
