<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

interface SignalHandler
{
    /**
     * Register signal handlers.
     *
     * @return $this
     */
    public function registerSignalHandlers(
        ?callable $onInterrupt = null,
        ?callable $onPause = null,
        ?callable $onHup = null
    );
}
