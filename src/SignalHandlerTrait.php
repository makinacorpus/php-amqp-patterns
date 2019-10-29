<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

trait SignalHandlerTrait /* implements SignalHandler */
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
    ) {
        if (!\function_exists('pcntl_signal')) {
            throw new \Exception("You must install 'pcntl' extension");
        }

        \pcntl_signal(SIGTERM, function () use ($onInterrupt) {
            $this->onInterrupt();
            if ($onInterrupt) {
                \call_user_func($onInterrupt);
            }
        });

        \pcntl_signal(SIGINT, function () use ($onInterrupt) {
            $this->onInterrupt();
            if ($onInterrupt) {
                \call_user_func($onInterrupt);
            }
        });

        /*
         * It seems to fail when run in Symfony console command.
         *
        \pcntl_signal(SIGSTOP, static function () use ($onPause) {
            $subscriber->onStop();
            if ($onPause) {
                \call_user_func($onPause);
            }
        });
         */

        /*
         * It seems to fail when run in Symfony console command.
         *
        \pcntl_signal(SIGHUP, function () use ($onHup) {
            $subscriber->onHup();
            if ($onHup) {
                \call_user_func($onHup);
            }
        });
         */

        return $this;
    }

    /**
     * React to sigterm or sigint (cleanup and stop)
     */
    public abstract function onInterrupt(): void;

    /**
     * React to sighup (restart)
     */
    public function onHup(): void
    {
        throw new \Exception("I am not implemented.");
    }

    /**
     * React to sigstop (pause)
     */
    public function onStop(): void
    {
        throw new \Exception("I am not implemented.");
    }
}
