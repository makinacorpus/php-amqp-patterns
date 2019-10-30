<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

/**
 * Message consumer interface suitable for all use cases.
 */
interface Consumer extends SignalHandler
{
    /**
     * Set on message received callback.
     *
     * AMQPMessage refers to \PhpAmqpLib\Message\AMQPMessage.
     *
     * @param callback $onMessage
     *   Signature can be either one of:
     *     - in case ack is enabled (default):
     *       function (AMQPMessage $message, callable $ack, callable $reject): void;
     *       Where the $reject callable signature is: function (bool $requeue = true): void;
     *     - in case ack is disabled (withAck(false) was called):
     *       function (AMQPMessage $message): void;
     *
     * @return $this
     */
    public function onMessage(callable $onMessage);

    /**
     * Set on error callback.
     *
     * You can pass null to restore default error handling.
     *
     * AMQPMessage refers to \PhpAmqpLib\Message\AMQPMessage.
     *
     * @param ?callback $onError
     *   Signature is: function (\Throwable $exception, AMQPMessage $message = null): void;
     *
     * @return $this
     */
    public function onError(?callable $onError);

    /**
     * Run consumer
     */
    public function run(): void;
}
