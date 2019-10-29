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
final class FanoutSubscriberSampleCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'amqp-pattern:sample:fanout-subscriber';

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
        $this->addOption('exchange', null, InputOption::VALUE_REQUIRED, "Exchange on which to connect", "my_fanout_exchange");
        $this->addOption('show-properties', null, InputOption::VALUE_NONE, "Show properties in output");
    }

    /**
     * Format header line.
     */
    private function formatProperty($key, $value, $indent = 0): array
    {
        // C'est moche, mais ça marche, à peu près.
        $ret = [];
        $prefix = $indent ? \str_repeat(' ', $indent).' - ' : '';
        if (\is_array($value)) {
            $ret[] = $prefix.$key.':';
            foreach ($value as $name => $child) {
                foreach ($this->formatProperty($name, $child, $indent+1) as $line) {
                    $ret[] = $line;
                }
            }
        } else {
            $ret[] = $prefix.$key.': '.$value;
        }
        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $exchange = $input->getOption('exchange');
        $output->writeln("<comment>Using '{$exchange}' exchange.</comment>");

        $showHeaders = (bool)$input->getOption('show-properties');

        $this
            ->factory
            ->createFanoutSubscriber($exchange)
            ->callback(function (AMQPMessage $message) use ($output, $showHeaders) {
                if ($showHeaders) {
                    $output->writeln(\sprintf("[%s] message received:", (new \DateTime())->format('Y-m-d H:i:s')));
                    $output->writeln($this->formatProperty('properties', $message->get_properties()));
                    $output->writeln($this->formatProperty('body', $message->getBody()));
                } else {
                    $output->writeln(\sprintf("[%s] %s", (new \DateTime())->format('Y-m-d H:i:s'), $message->body));
                }
            })
            ->registerSignalHandlers(static function () use ($output) {
                $output->writeln('<comment>... process killed</comment>');
            })
            ->run()
        ;
    }
}
