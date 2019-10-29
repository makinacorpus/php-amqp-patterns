<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

use PhpAmqpLib\Message\AMQPMessage;

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
final class DefaultConsumer implements Consumer
{
    use ClientTrait, SignalHandlerTrait;

    /** @var callable */
    private $onMessage;

    /** @var ?callable */
    private $onError;

    /** @var ?string */
    private $queueName;

    /** @var ?string[] */
    private $bindingKeys;

    /** @var bool */
    private $withAck = false;

    /**
     * {@inheritdoc}
     */
    public function callback(callable $onMessage): Consumer
    {
        $this->raiseErrorIfRunning();

        if ($this->onMessage) {
            throw new \LogicException("You cannot call ::callback() twice");
        }

        $this->onMessage = $onMessage;

        return $this;
    }

    /**
     * Set queue name
     *
     * @param ?string $queueName
     *   Can be set to null or an empty string for an anonymous queue
     *
     * @return $this
     */
    public function queue(?string $queueName): Consumer
    {
        $this->raiseErrorIfRunning();

        $this->queueName = $queueName;

        return $this;
    }

    /**
     * Set ACK mode
     *
     * For event consumer, for publish/subscribe usage, we don't need ack
     *
     * @return $this;
     */
    public function bindingKeys(?array $bindingKeys): Consumer
    {
        $this->raiseErrorIfRunning();

        $this->bindingKeys = $bindingKeys;

        return $this;
    }

    /**
     * Set ACK mode
     *
     * For event consumer, for publish/subscribe usage, we don't need ack
     *
     * @return $this;
     */
    public function withAck(bool $toggle = true): Consumer
    {
        $this->raiseErrorIfRunning();

        $this->withAck = $toggle;

        return $this;
    }

    /**
     * Run internal loop.
     */
    private function runLoop()
    {
        if ($this->running) {
            return;
        }

        $this->running = true;

        while ($this->running && $this->channel->is_consuming()) {
            $this->channel->wait();
        }

        $this->running = false;
    }

    /**
     * Prepare queue
     */
    private function prepareQueue(): string
    {
        if ($this->queueName) {
            // User asked for a queue name, declare it as durable and non
            // exclusive, so the queue will remain and accept other clients
            // connections.
            $this->channel->queue_declare($this->queueName, false, true, false, false);
            $queueName = $this->queueName;
        } else {
            // Mark anonymous queues as being exclusive and not durable and such
            // it will be destroyed once the connection ends.
            list($queueName) = $this->channel->queue_declare("", false, false, true, false);
        }

        $this->declareExchange();

        // We must always bind, the only exception is when a client connects
        // without an exchange (default exchange). You should not do this.
        if ($this->bindingKeys) {
            // Bind routing keys, perfect for direct and topic exchange.
            foreach ($this->bindingKeys ?? [] as $bindingKey) {
                $this->channel->queue_bind($queueName, $this->exchange, $bindingKey);
            }
        } else {
            // Consider the queue name as the binding key in case we are working
            // with a direct exchange.
            $this->channel->queue_bind($queueName, $this->exchange, $queueName);
        }

        return $queueName;
    }

    /**
     * Consume with ack (worker)
     */
    private function consumeWithAck(): void
    {
        $queueName = $this->prepareQueue();

        $this->channel->basic_consume(
            $queueName, '', false, false, false, false,

            // What happens when a message is received.
            function (AMQPMessage $message): void {
                $deliveryTag = $message->getDeliveryTag();

                // We are going to set this flag whenever we send an ack or a
                // reject, throwing a response twice for the same delivery tag
                // will create low level protocol errors, and we don't want that.
                $responseSent = false;

                $ack = function () use ($deliveryTag, &$responseSent): void {
                    $this->channel->basic_ack($deliveryTag);
                    $responseSent = true;
                };

                $reject = function (bool $reQueue = true) use ($deliveryTag, &$responseSent): void {
                    $this->channel->basic_reject($deliveryTag, $reQueue);
                    $responseSent = true;
                };

                try {
                    \call_user_func($this->onMessage, $message, $ack, $reject);
                    // Allow user code to skip basic ack, if nothing failed just
                    // send the ack on its behalf.
                    if (!$responseSent) {
                        $this->channel->basic_ack($deliveryTag, true);
                        $responseSent = true;
                    }
                    // @todo, should we handle exceptions ourselves?
                    //   I guess not, it probably should be forwarded to a custom
                    //   user-registered error handler.
                } finally {
                    if (!$responseSent) {
                        $this->channel->basic_reject($deliveryTag, true);
                    }
                }
            }
        );
    }

    /**
     * Consume without ack (fanout/subscriber) 
     */
    private function consumeWithoutAck(): void
    {
        $queueName = $this->prepareQueue();

        $this->channel->basic_consume($queueName, '', false, true, false, false, $this->onMessage);
    }

    /**
     * {@inheritdoc}
     */
    final public function run(): void
    {
        if (!$this->onMessage) {
            throw new \LogicException("You must call the ::callback() function prior running the subscriber");
        }

        $this->raiseErrorIfRunning();

        if ($this->withAck) {
            $this->consumeWithAck();
        } else {
            $this->consumeWithoutAck();
        }

        $this->runLoop();
    }

    /**
     * {@inheritdoc}
     */
    public function onInterrupt(): void
    {
        // Do not close the connection from here, let it seamlessly terminate
        // its current loop and shutdown will follow gracefully. Depending
        // upon the time necessary to complete the task, shutdown may not be
        // immediate.
        $this->running = false;
    }

    /**
     * {@inheritdoc}
     */
    public function onHup(): void
    {
        $this->running = true;
        $this->runLoop();
    }

    /**
     * {@inheritdoc}
     */
    public function onStop(): void
    {
        $this->running = false;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->channel->close();
    }
}
