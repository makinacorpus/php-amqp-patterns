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
final class TaskPublisher implements Publisher
{
    /** @var AMQPChannel */
    private $channel;

    /** @var bool */
    private $doDeclareExchance = true;

    /** @var ?string */
    private $exchange;

    /** @var string */
    private $exchangeType = PatternFactory::EXCHANGE_DIRECT;

    /** @var string */
    private $routingKey;

    /**
     * Default constructor
     */
    public function __construct(AMQPChannel $channel, ?string $exchange = null, bool $doDeclareExchange = true)
    {
        $this->channel = $channel;
        $this->exchange = $exchange;
        $this->doDeclareExchance = $doDeclareExchange;
    }

    /**
     * Set default routing key if none provided at publish() call.
     *
     * @param ?string $routingKey
     *   Can be set to null to drop
     *
     * @return $this
     */
    public function defaultRoutingKey(?string $routingKey): TaskPublisher
    {
        $this->routingKey = $routingKey;

        return $this;
    }

    /**
     * Set exchange type
     */
    public function exchangeType(string $exchangeType): TaskPublisher
    {
        PatternFactory::isExchangeTypeValid($exchangeType);

        $this->exchangeType = $exchangeType;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $data, array $properties = [], ?string $routingKey = null): void
    {
        if ($this->exchange && $this->doDeclareExchance) {
            // Declare a channel with sensible defaults.
            $this->channel->exchange_declare($this->exchange, $this->exchangeType, false, true, false);
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
