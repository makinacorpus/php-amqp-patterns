<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Queue factory: creates queues.
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
     * Is given exchange type valid
     */
    public static function isExchangeTypeValid(string $exchangeType, bool $raiseErrorIfInvalid = true): bool
    {
        $allowed = [
            self::EXCHANGE_DIRECT,
            self::EXCHANGE_FANOUT,
            self::EXCHANGE_TOPIC,
        ];

        if (!\in_array($exchangeType, $allowed)) {
            if ($raiseErrorIfInvalid) {
                throw new \InvalidArgumentException(\sprintf(
                    "'%s': invalid exchange type, allowed valus are: '%s'",
                    $exchangeType, \implode("', '", $allowed)
                ));
            }
            return false;
        }
        return true;
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
     * Creates a publisher
     */
    public function createPublisher(?string $exchange = null): DefaultPublisher
    {
        return new DefaultPublisher($this->channel(), $exchange, true);
    }

    /**
     * Creates a consumer
     */
    public function createWorker(?string $exchange = null): TaskWorker
    {
        return new TaskWorker($this->channel(), $exchange, true);
    }

    /**
     * Topic publisher is the same as task publisher for a topic exchange.
     */
    public function createTopicPublisher(string $exchange): DefaultPublisher
    {
        return $this
            ->createPublisher($exchange)
            ->exchangeType(self::EXCHANGE_TOPIC)
        ;
    }

    /**
     * Topic worker is the same as task worker except that its queue will be
     * bound to one or more binding keys, on a topic exchange.
     */
    public function createTopicWorker(string $exchange, array $bindingKeys, ?string $queueName = null): TaskWorker
    {
        return $this
            ->createWorker($exchange)
            ->exchangeType(self::EXCHANGE_TOPIC)
            ->queue($queueName)
            ->bindingKeys($bindingKeys)
            ->withAck()
        ;
    }

    /**
     * Create task publisher. Routing key here must be a queue name.
     */
    public function createTaskPublisher(string $routingKey, ?string $exchange = null): Publisher
    {
        return $this
            ->createPublisher($exchange)
            ->exchangeType(self::EXCHANGE_DIRECT)
            ->defaultRoutingKey($routingKey)
        ;
    }

    /**
     * Create task worker.
     */
    public function createTaskWorker(string $queueName, ?string $exchange = null): TaskWorker
    {
        return $this
            ->createWorker($exchange)
            ->exchangeType(self::EXCHANGE_DIRECT)
            ->queue($queueName)
            ->withAck()
        ;
    }

    /**
     * Create publish/subscribe-like publisher.
     */
    public function createFanoutPublisher(string $exchange): Publisher
    {
        return $this
            ->createPublisher($exchange)
            ->exchangeType(self::EXCHANGE_FANOUT)
        ;
    }

    /**
     * Create publish/subscribe-like subscriber.
     */
    public function createFanoutSubscriber(string $exchange): FanoutSubscriber
    {
        return new FanoutSubscriber($this->channel(), $exchange, true);
    }
}
