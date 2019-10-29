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
final class DefaultPublisher implements Publisher
{
    use ClientTrait;

    /** @var string */
    private $routingKey;

    /**
     * Default constructor
     */
    public function __construct(AMQPChannel $channel, ?string $exchange = null)
    {
        $this->channel = $channel;
        $this->exchange = $exchange;
    }

    /**
     * Set default routing key if none provided at publish() call.
     *
     * @param ?string $routingKey
     *   Can be set to null to drop
     *
     * @return $this
     */
    public function defaultRoutingKey(?string $routingKey): DefaultPublisher
    {
        $this->routingKey = $routingKey;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $data, array $properties = [], ?string $routingKey = null): void
    {
        $this->declareExchange();

        $message = new AMQPMessage($data, $properties);

        $this->channel->basic_publish($message, $this->exchange ?? '', $routingKey ?? $this->routingKey ?? '');
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->channel->close();
    }
}
