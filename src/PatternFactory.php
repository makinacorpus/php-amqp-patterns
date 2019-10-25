<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Queue factory: creates queues.
 *
 * @todo
 *   Current model is wrong, we should implement pattern directly, such
 *   as PUB/SUB with methods such as:
 *     -> createSubscriber() // pub/sub consumer
 *     -> createPublisher() // pub/sub publisher
 *     -> createWorker() // worker consumer
 *     -> create?() // worker published
 *     -> ...
 *   
 *   That would return custom objects with such methods depending upon
 *   the selected pattern, of course:
 *     -> declareExchange()
 *     -> declareQueue(?bindTo)
 *     -> getRawChannel(): AMQPChannel
 *     -> publish()
 *     -> consume(function?)
 *     -> stop()
 *     -> ...
 */
final class PatternFactory
{
    const EXCHANGE_DIRECT = 'direct';
    const EXCHANGE_FANOUT = 'fanout';
    const EXCHANGE_TOPIC = 'topic';

    const DEFAULT_HOST = [
        'host' => '127.0.0.1',
        'port' => '5672',
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => null,
    ];

    /** @var string[][] */
    private $hostList = [];

    /** @var AMQPStreamConnection */
    private $connection;

    /**
     * Default constructor
     */
    public function __construct(array $hostList = [], bool $randomizeHostList = false)
    {
        if (!$hostList) {
            $this->hostList[] = self::DEFAULT_HOST;
        } else {
            $hostList = \array_map(
                static function (array $host) {
                    return $host + self::DEFAULT_HOST;
                },
                $hostList
            );
            if ($randomizeHostList) {
                \shuffle($hostList);
            }
            $this->hostList = $hostList;
        }
    }

    /**
     * Fetch raw connection
     */
    public function connection(): AMQPStreamConnection
    {
        return $this->connection ?? (
            $this->connection = AMQPStreamConnection::create_connection($this->hostList)
        );
    }

    /**
     * Fetch or create a channel.
     *
     * Without a $channelId provide, it will create a new anonymous channel.
     */
    public function channel(?int $channelId = null): AMQPChannel
    {
        return $this->connection()->channel($channelId);
    }

    /**
     * Create task publisher.
     */
    public function createTaskPublisher(string $routingKey, ?string $exchange = null): TaskPublisher
    {
        return new TaskPublisher($this->channel(), $routingKey, $exchange, true);
    }

    /**
     * Create task worker.
     */
    public function createTaskWorker(string $queue, ?string $exchange = null): TaskWorker
    {
        return new TaskWorker($this->channel(), $queue, $exchange, true);
    }

    /**
     * Create publish/subscribe-like publisher.
     */
    public function createFanoutPublisher(string $exchange): FanoutPublisher
    {
        return new FanoutPublisher($this->channel(), $exchange, true);
    }

    /**
     * Create publish/subscribe-like subscriber.
     */
    public function createFanoutSubscriber(string $exchange): FanoutSubscriber
    {
        return new FanoutSubscriber($this->channel(), $exchange, true);
    }
}
