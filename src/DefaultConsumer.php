<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

use PhpAmqpLib\Message\AMQPMessage;

/**
 * Consumer implementation with error handling.
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
    public function onMessage(callable $onMessage)
    {
        $this->raiseErrorIfRunning();

        if ($this->onMessage) {
            throw new \LogicException(\sprintf("You cannot call %s::onMessage() twice", __CLASS__));
        }

        $this->onMessage = $onMessage;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function onError(?callable $onError)
    {
        $this->raiseErrorIfRunning();

        $this->onError = $onError;

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
    public function queue(?string $queueName): DefaultConsumer
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
    public function bindingKeys(?array $bindingKeys): DefaultConsumer
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
    public function withAck(bool $toggle = true): DefaultConsumer
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

        // This should be configurable, but it's actually common sense to avoid
        // giving more than one message to one consumer: if we don't, when we
        // start another consumer, it won't get any work to do since eveything
        // will be already given to a single consumer.
        $this->channel->basic_qos(null, 1, null);

        $this->channel->basic_consume(
            $queueName, '', false, false, false, false,

            // We wrap user handler with a more advanced one that will always
            // fallback with ack/reject handling.
            function (AMQPMessage $message): void {
                $deliveryTag = $message->getDeliveryTag();

                // We are going to set this flag whenever we send an ack or a
                // reject, throwing a response twice for the same delivery tag
                // will create low level protocol errors, and we don't want that.
                $responseSent = false;
                $errorWasFatal = false;

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
                } catch (\Throwable $e) {
                    // @todo we should catch at least protocol errors, deconnection
                    //   and such and do not allow user error for those. For example,
                    //   on a deconnection, we should just break here.
                    if ($this->onError) {
                        \call_user_func($this->onError, $e, $message);
                    } else {
                        $errorWasFatal = true;
                        throw $e;
                    }
                } finally {
                    if (!$responseSent) {
                        // Do not requeue if error was fatal and unhandled.
                        // Message will go to any dead letter queue it was
                        // supposed to go.
                        // @todo this should be configurable maybe? 
                        $this->channel->basic_reject($deliveryTag, !$errorWasFatal);
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

        $this->channel->basic_consume($queueName, '', false, true, false, false,
            // We wrap user handler for error handling.
            function (AMQPMessage $message): void {
                try {
                    \call_user_func(
                        $this->onMessage,
                        $message,
                        static function (): void {
                            throw new \LogicException("You cannot call \$ack(), ack is disabled.");
                        },
                        static function (): void {
                            throw new \LogicException("You cannot call \$reject(), ack is disabled.");
                        }
                    );
                } catch (\Throwable $e) {
                    if ($this->onError) {
                        \call_user_func($this->onError, $e, $message);
                    }
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    final public function run(): void
    {
        if (!$this->onMessage) {
            throw new \LogicException(\sprintf("You must call the %s::onMessage() function prior running the subscriber", __CLASS__));
        }

        if ($this->running) {
            return;
        }

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
