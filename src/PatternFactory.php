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
     * Topic publisher is the same as task publisher except it will send on a
     * topic exchange.
     */
    public function createTopicPublisher(string $exchange): TaskPublisher
    {
        return (new TaskPublisher($this->channel(), $exchange, true))
            ->exchangeType(self::EXCHANGE_TOPIC)
        ;
    }

    /**
     * Topic worker is the same as task worker except that its queue will b
     * bound to one or more binding keys, on a topic exchange.
     */
    public function createTopicWorker(string $exchange, array $bindingKeys, ?string $queueName = null): TaskWorker
    {
        return (new TaskWorker($this->channel(), $exchange, true))
            ->exchangeType(self::EXCHANGE_TOPIC)
            ->queue($queueName)
            ->bindingKeys($bindingKeys)
            ->withAck(true)
        ;
    }

    /**
     * Create task publisher. Routing key here must be a queue name.
     */
    public function createTaskPublisher(string $routingKey, ?string $exchange = null): TaskPublisher
    {
        return (new TaskPublisher($this->channel(), $exchange, true))
            ->exchangeType(self::EXCHANGE_DIRECT)
            ->defaultRoutingKey($routingKey)
        ;
    }

    /**
     * Create task worker.
     */
    public function createTaskWorker(string $queueName, ?string $exchange = null): TaskWorker
    {
        return (new TaskWorker($this->channel(), $exchange, true))
            ->exchangeType(self::EXCHANGE_DIRECT)
            ->queue($queueName)
            ->withAck(true)
        ;
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
