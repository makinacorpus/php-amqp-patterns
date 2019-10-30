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
        $this->addOption('binding-key', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, "Binding key to listen for", ['my_task_queue']);
        $this->addOption('exchange', null, InputOption::VALUE_OPTIONAL, "Exchange on which to connect", "");
        $this->addOption('no-sleep', null, InputOption::VALUE_NONE, "Disable sleep when processing message");
        $this->addOption('queue', null, InputOption::VALUE_REQUIRED, "Queue name on which to attach to", 'my_task_real_queue');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $exchange = $input->getOption('exchange');
        $output->writeln("<comment>Using '{$exchange}' exchange.</comment>");

        $queueName = $input->getOption('queue');
        $bindingKeys = $input->getOption('binding-key');

        $output->writeln("<comment>I am working on the '{$queueName}' queue.</comment>");
        $output->writeln("<comment>".\sprintf("I am binding to '%s' binding keys.", \implode("', '", $bindingKeys))."</comment>");
        $output->writeln("<comment>Hit CTRL+C to quit.</comment>");
        $output->writeln("");
        $output->writeln("<comment>Send a message containing:</comment>");
        $output->writeln("<comment> - 'invalid' to trigger a reject without requeing</comment>");
        $output->writeln("<comment> - 'reject' to trigger a reject with requeing</comment>");
        $output->writeln("<comment> - 'error' to trigger an random exception to be raised</comment>");
        $output->writeln("<comment> - Any other value will trigger an ack</comment>");

        $doSleep = !$input->getOption('no-sleep');

        $this
            ->factory
            ->createTaskWorker($exchange, $bindingKeys, $queueName)
            ->onMessage(function (AMQPMessage $message, callable $ack, callable $reject) use ($output, $doSleep) {
                $output->writeln("");

                if ($doSleep) {
                    $output->writeln("Waiting for 3 seconds before processing...");
                    $output->writeln("Hit CTRL+C now will wait for the processing to end before exiting.");
                    \sleep(3);
                }

                $output->writeln(\sprintf("[%s] %s", (new \DateTime())->format('Y-m-d H:i:s'), $message->body));

                if ($message->body === 'error') {
                    throw new \LogicException("Bouh");
                }
                if ($message->body === 'invalid') {
                    $output->writeln("OK, rejecting without requeue.");
                    $reject(false);
                    return;
                }
                if ($message->body === 'reject') {
                    if ($message->delivery_info['redelivered']) {
                        $output->writeln("OK, message is a redelivery, rejecting without requeue.");
                        $reject(false);
                    } else {
                        $output->writeln("OK, rejecting with requeue.");
                        $reject(true);
                    }
                    return;
                }
                $output->writeln("OK, I'm done.");
                $ack();
            })
            ->onError(function (\Throwable $e, AMQPMessage $message) use ($output) {
                $output->writeln('<error>'.\sprintf("An exception was raised: %s", $e->getMessage()).'</error>');
            })
            ->registerSignalHandlers(static function () use ($output) {
                $output->writeln('<comment>... process killed</comment>');
            })
            ->run()
        ;
    }
}
