<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns\Sample;

use MakinaCorpus\AMQP\Patterns\PatternFactory;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Messages consumer
 */
final class TaskWorkerSampleCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'amqp-pattern:sample:task-worker';

    /** @var PatternFactory */
    private $factory;

    /** @var string */
    private $exchange;

    /**
     * Default constructor
     */
    public function __construct(PatternFactory $factory, ?string $exchange = null)
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
        $this->addOption('no-sleep', null, InputOption::VALUE_NONE, "Disable sleep when processing message");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $doSleep = !$input->getOption('no-sleep');

        $output->writeln("<comment>Using '{$this->exchange}' exchange.</comment>");

        $output->writeln("<comment>I am working on the 'my_task_queue' queue.</comment>");
        $output->writeln("<comment>Send me 'invalid' for a reject without requeing.</comment>");
        $output->writeln("<comment>Send me 'reject' for a reject with requeing.</comment>");
        $output->writeln("<comment>Type CTRL+C when I tell you to do so for a 'reject' with requeing.</comment>");
        $output->writeln("<comment>Send me anything else for a basic ack.</comment>");

        $this
            ->factory
            ->createTaskWorker('my_task_queue', $this->exchange)
            ->callback(function (AMQPMessage $message, callable $ack, callable $reject) use ($output, $doSleep) {
                $output->writeln("");

                if ($doSleep) {
                    $output->writeln("Waiting for 3 seconds before processing, hit CTRL+C anytime !");
                    \sleep(3);
                }

                $output->writeln(\sprintf("[%s] %s", (new \DateTime())->format('Y-m-d H:i:s'), $message->body));

                if ($message->body === 'invalid') {
                    $output->writeln("OK, rejecting without requeue.");
                    $reject(false);
                    return;
                }
                if ($message->body === 'reject') {
                    $output->writeln("OK, rejecting with requeue.");
                    $reject(true);
                    return;
                }
                $output->writeln("OK, I'm done.");
                $ack();
            })
            ->registerSignalHandlers(static function () use ($output) {
                $output->writeln('<comment>... process killed</comment>');
            })
            ->run()
        ;
    }
}
