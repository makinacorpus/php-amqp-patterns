<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

use PhpAmqpLib\Channel\AMQPChannel;

/**
 * All clients will use all of this.
 */
trait ClientTrait
{
    /** @var AMQPChannel */
    private $channel;

    /** @var bool */
    private $doDeclareExchange = true;

    /** @var string */
    private $exchange;

    /** @var bool */
    private $exchangeIsDurable = true;

    /** @var string */
    private $exchangeType = PatternFactory::EXCHANGE_DIRECT;

    /** @var bool */
    private $running = false;

    /**
     * Default constructor
     */
    public function __construct(AMQPChannel $channel, ?string $exchange = null)
    {
        $this->channel = $channel;
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
     * Set exchange type
     *
     * @return $this
     */
    public function exchangeType(string $exchangeType): self
    {
        $this->raiseErrorIfRunning();
        PatternFactory::isExchangeTypeValid($exchangeType);

        $this->exchangeType = $exchangeType;

        return $this;
    }

    /**
     * Set exchange type
     *
     * @return $this
     */
    public function doDeclareExchange(bool $toggle = true): self
    {
        $this->raiseErrorIfRunning();

        $this->doDeclareExchange = $toggle;

        return $this;
    }

    /**
     * Set exchange type
     *
     * @return $this
     */
    public function exchangeIsDurable(bool $toggle = true): self
    {
        $this->raiseErrorIfRunning();

        $this->exchangeIsDurable = $toggle;

        return $this;
    }

    /**
     * Declare exchange if necessary
     */
    private function declareExchange(): void
    {
        if ($this->exchange && $this->doDeclareExchange) {
            $this->channel->exchange_declare(
                $this->exchange,
                $this->exchangeType,
                false, // passive
                $this->exchangeIsDurable,
                false // auto delete
            );
        }
    }
}
