<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Consumer for the worker pattern from RabbitMQ documentation.
 *
 * If you don't specify an exchange, default will be "amq.direct".
 *
 * In this pattern implementation, we do not bind any queue to the exchange,
 * it wouldn't mean anything, we just send those messages into this queue.
 * For advanced routing, use RoutingTaskWorker.
 *
 * @see https://www.rabbitmq.com/tutorials/tutorial-two-php.html
 */
final class TaskPublisher
{
    /** @var AMQPChannel */
    private $channel;

    /** @var bool */
    private $doDeclareExchance = true;

    /** @var ?string */
    private $exchange;

    /** @var string */
    private $routingKey;

    /**
     * Default constructor
     */
    public function __construct(AMQPChannel $channel, string $routingKey, ?string $exchange = null, bool $doDeclareExchange = true)
    {
        $this->channel = $channel;
        $this->exchange = $exchange;
        $this->doDeclareExchance = $doDeclareExchange;
        $this->routingKey = $routingKey;
    }

    /**
     * Publish a message
     *
     * Data must have been already encoded if necessary
     */
    public function publish(string $data, array $properties = [], ?string $routingKey = null): void
    {
        if ($this->exchange && $this->doDeclareExchance) {
            // Declare a channel with sensible defaults for the worker pattern.
            $this->channel->exchange_declare($this->exchange, PatternFactory::EXCHANGE_DIRECT, false, true, false);
        }

        $message = new AMQPMessage($data, $properties);

        $this->channel->basic_publish($message, $this->exchange ?? '', $routingKey ?? $this->routingKey);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->channel->close();
    }
}
