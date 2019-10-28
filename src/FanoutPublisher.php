<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Consumer for the pub/sub pattern from RabbitMQ documentation
 *
 * @see https://www.rabbitmq.com/tutorials/tutorial-three-php.html
 */
final class FanoutPublisher implements Publisher
{
    /** @var AMQPChannel */
    private $channel;

    /** @var bool */
    private $doDeclareExchance = true;

    /** @var string */
    private $exchange;

    /**
     * Default constructor
     */
    public function __construct(AMQPChannel $channel, string $exchange, bool $doDeclareExchange = true)
    {
        $this->channel = $channel;
        $this->exchange = $exchange;
        $this->doDeclareExchance = $doDeclareExchange;
    }

    /**
     * {@inheritdoc}
     *
     * In this implementation, $routingKey will be ignored.
     */
    public function publish(string $data, array $properties = [], ?string $routingKey = null): void
    {
        if ($this->doDeclareExchance) {
            // Declare a channel with sensible defaults for the
            // publish/subscribe pattern.
            $this->channel->exchange_declare($this->exchange, PatternFactory::EXCHANGE_FANOUT, false, false, false);
        }

        $message = new AMQPMessage($data, $properties);

        $this->channel->basic_publish($message, $this->exchange);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->channel->close();
    }
}
