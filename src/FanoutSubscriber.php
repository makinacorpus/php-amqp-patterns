<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

use PhpAmqpLib\Channel\AMQPChannel;

/**
 * Consumer for the pub/sub pattern from RabbitMQ documentation
 *
 * @see https://www.rabbitmq.com/tutorials/tutorial-three-php.html
 */
final class FanoutSubscriber
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
     * Set callback
     *
     * @param callback $callback
     *   First callback argument is the message
     */
    public function callback(callable $callback): FanoutSubscriber
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

        // Declare an anonymous queue, bound to the channel, that will
        // be destroyed with the session.
        list($queueName) = $this->channel->queue_declare("", false, false, true, false);

        if ($this->doDeclareExchance) {
            // Declare a channel with sensible defaults for the
            // publish/subscribe pattern.
            $this->channel->exchange_declare($this->exchange, PatternFactory::EXCHANGE_FANOUT, false, false, false);
        }

        $this->channel->basic_consume($queueName, '', false, true, false, false, $this->callback);
        $this->channel->queue_bind($queueName, $this->exchange);

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
