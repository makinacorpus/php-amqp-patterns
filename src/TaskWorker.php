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
 *
 * @see https://www.rabbitmq.com/tutorials/tutorial-two-php.html
 */
final class TaskWorker
{
    use SignalHandler;

    /** @var AMQPChannel */
    private $channel;

    /** @var bool */
    private $doDeclareExchance = true;

    /** @var string */
    private $exchange;

    /** @var callable */
    private $callback;

    /** @var bool */
    private $running = false;

    /** @var string */
    private $queue;

    /**
     * Default constructor
     */
    public function __construct(AMQPChannel $channel, string $queue, ?string $exchange = null, bool $doDeclareExchange = true)
    {
        $this->channel = $channel;
        $this->exchange = $exchange;
        $this->doDeclareExchance = $doDeclareExchange;
        $this->queue = $queue;
    }

    /**
     * Set callback
     *
     * @param callback $callback
     *   First callback argument is the message
     */
    public function callback(callable $callback): TaskWorker
    {
        if ($this->callback) {
            throw new \LogicException("You cannot call ::callback() twice");
        }
        $this->callback = $callback;

        return $this;
    }

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
     * Run consumer
     */
    public function run(): void
    {
        if (!$this->callback) {
            throw new \LogicException("You must call the ::callback() function prior running the subscriber");
        }

        $this->channel->queue_declare($this->queue, false, true, false, false);

        if ($this->exchange && $this->doDeclareExchance) {
            // Declare a channel with sensible defaults for the worker pattern.
            $this->channel->exchange_declare($this->exchange, PatternFactory::EXCHANGE_DIRECT, false, true, false);
        }

        $this->channel->basic_consume(
            $this->queue, '', false, false, false, false,

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
                    \call_user_func($this->callback, $message, $ack, $reject);
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
