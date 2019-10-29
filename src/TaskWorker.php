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

    /** @var callable */
    private $callback;

    /** @var AMQPChannel */
    private $channel;

    /** @var bool */
    private $doDeclareExchance = true;

    /** @var string */
    private $exchange;

    /** @var string */
    private $exchangeType = PatternFactory::EXCHANGE_DIRECT;

    /** @var ?string */
    private $queueName;

    /** @var ?string[] */
    private $bindingKeys;

    /** @var bool */
    private $withAck = false;

    /** @var bool */
    private $running = false;

    /**
     * Default constructor
     */
    public function __construct(AMQPChannel $channel, ?string $exchange = null, bool $doDeclareExchange = true)
    {
        $this->channel = $channel;
        $this->doDeclareExchance = $doDeclareExchange;
        $this->exchange = $exchange;
    }

    /**
     * Raise an exception
     */
    private function raiseErrorIfRunning()
    {
        if ($this->running) {
            throw new \LogicException("You cannot change consumer state once running");
        }
    }

    /**
     * Set callback
     *
     * @param callback $callback
     *   First callback argument is the message
     *
     * @return $this
     */
    public function callback(callable $callback): TaskWorker
    {
        $this->raiseErrorIfRunning();

        if ($this->callback) {
            throw new \LogicException("You cannot call ::callback() twice");
        }

        $this->callback = $callback;

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
    public function queue(?string $queueName): TaskWorker
    {
        $this->raiseErrorIfRunning();

        $this->queueName = $queueName;

        return $this;
    }

    /**
     * Set exchange type
     */
    public function exchangeType(string $exchangeType): TaskWorker
    {
        $this->raiseErrorIfRunning();
        PatternFactory::isExchangeTypeValid($exchangeType);

        $this->exchangeType = $exchangeType;

        return $this;
    }

    /**
     * Set ACK mode
     *
     * For event consumer, for publish/subscribe usage, we don't need ack
     *
     * @return $this;
     */
    public function bindingKeys(?array $bindingKeys): TaskWorker
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
    public function withAck(bool $toggle = true): TaskWorker
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
    protected function prepareQueue(): string
    {
        if ($this->queueName) {
            $this->channel->queue_declare($this->queueName, false, true, false, false);
            $queueName = $this->queueName;
        } else {
            list($queueName) = $this->channel->queue_declare("", false, false, false, false);
        }

        if ($this->exchange && $this->doDeclareExchance) {
            // Declare a channel with sensible defaults.
            $this->channel->exchange_declare($this->exchange, $this->exchangeType, false, true, false);
        }

        if ($this->bindingKeys) {
            // Bind routing keys.
            foreach ($this->bindingKeys ?? [] as $bindingKey) {
                $this->channel->queue_bind($queueName, $this->exchange, $bindingKey);
            }
        } else {
            // Consider the queue name as the binding key.
            $this->channel->queue_bind($queueName, $this->exchange, $queueName);
        }

        return $queueName;
    }

    /**
     * Run consumer
     */
    final public function run(): void
    {
        if (!$this->callback) {
            throw new \LogicException("You must call the ::callback() function prior running the subscriber");
        }

        $this->raiseErrorIfRunning();

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
