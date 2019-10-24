<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns\Sample;

final class Env
{
    const EXCHANGE_PUBSUB = 'AMQP_PATTERNS_SAMPLE_EXCHANGE_PUBSUB';
    const EXCHANGE_WORKER = 'AMQP_PATTERNS_SAMPLE_EXCHANGE_WORKER';
    const SERVER_HOST = 'AMQP_PATTERNS_SAMPLE_SERVER_HOST';
    const SERVER_PASSWORD = 'AMQP_PATTERNS_SAMPLE_SERVER_PASSWORD';
    const SERVER_PORT = 'AMQP_PATTERNS_SAMPLE_SERVER_PORT';
    const SERVER_USER = 'AMQP_PATTERNS_SAMPLE_SERVER_USER';

    public static function getSamplePubSubExchange(): string
    {
        if ($value = \getenv(self::EXCHANGE_PUBSUB)) {
            return $value;
        }
        return 'amqp_patterns_sample_pubsub';
    }

    public static function getSampleWorkerExchange(): string
    {
        if ($value = \getenv(self::EXCHANGE_WORKER)) {
            return $value;
        }
        return 'amqp_patterns_sample_worker';
    }
}
