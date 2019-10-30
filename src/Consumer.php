<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

/**
 * Message consumer interface suitable for all use cases.
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
