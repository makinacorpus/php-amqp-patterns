<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

/**
 * Consumer for the worker pattern from RabbitMQ documentation.
 *
 * If you don't specify an exchange, default will be "amq.direct".
 *
 * In this pattern implementation, we do not bind any queue to the exchange,
 * it wouldn't mean anything, we just send those messages into this queue.
 *
 * @see https://www.rabbitmq.com/tutorials/tutorial-two-php.html
 */
interface Consumer extends SignalHandler
{
    /**
     * Set callback
     *
     * @param callback $onMessage
     *   First callback argument is the message
     *
     * @return $this
     */
    public function callback(callable $onMessage): Consumer;

    /**
     * Run consumer
     */
    public function run(): void;
}
