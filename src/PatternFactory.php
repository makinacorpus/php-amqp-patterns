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
        // All options here are passed as-is to php-amqplib connection.
        'options' => [
            'lazy' => false,
            'timeout' => 5,
        ],
    ];

    /** @var string[][] */
    private $hostList = [];

    /** @var AMQPStreamConnection */
    private $connection;

    /** @var bool */
    private $doDeclareExchanges = true;

    /**
     * Default constructor
     */
    public function __construct(array $hostList = [], bool $randomizeHostList = false)
    {
        // Prune empty values with array_filter(), allowing fully automatic
        // configuration in Symfony with environement variables, without any
        // compiler pass or extension required.
        $hostList = \array_filter($hostList);
        if (!$hostList) {
            $this->hostList[] = self::DEFAULT_HOST;
        } else {
            $hostList = \array_map([Normalize::class, 'normalizeHost'], $hostList);
            if ($randomizeHostList) {
                \shuffle($hostList);
            }
            $this->hostList = $hostList;
        }
    }

    /**
     * Set auto-declare exchanges parameter.
     */
    public function autoDeclareExchanges(bool $toggle = true)
    {
        $this->doDeclareExchanges = $toggle;
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
     * Creates a publisher, unconfigured.
     */
    public function createPublisher(?string $exchange = null): DefaultPublisher
    {
        return new DefaultPublisher($this->channel(), $exchange);
    }

    /**
     * Creates a worker-type (handling ack/reject), unconfigured.
     */
    public function createConsumer(?string $exchange = null): DefaultConsumer
    {
        return new DefaultConsumer($this->channel(), $exchange);
    }

    /**
     * Topic publisher is the same as task publisher for a topic exchange.
     */
    public function createTopicPublisher(string $exchange): DefaultPublisher
    {
        return $this
            ->createPublisher($exchange)
            ->exchangeType(self::EXCHANGE_TOPIC)
            ->exchangeIsDurable(true)
            ->doDeclareExchange($this->doDeclareExchanges)
        ;
    }

    /**
     * Topic worker is the same as task worker except that its queue will be
     * bound to one or more binding keys, on a topic exchange.
     */
    public function createTopicWorker(string $exchange, array $bindingKeys, ?string $queueName = null): Consumer
    {
        return $this
            ->createConsumer($exchange)
            ->withAck(true)
            ->bindingKeys($bindingKeys)
            ->queue($queueName)
            ->exchangeType(self::EXCHANGE_TOPIC)
            ->exchangeIsDurable(true)
            ->doDeclareExchange($this->doDeclareExchanges)
        ;
    }

    /**
     * Create task publisher. Routing key is the default routing key if none
     * specified when publish() is called.
     */
    public function createTaskPublisher(string $exchange, ?string $routingKey = null): Publisher
    {
        return $this
            ->createPublisher($exchange)
            ->defaultRoutingKey($routingKey)
            ->exchangeType(self::EXCHANGE_DIRECT)
            ->exchangeIsDurable(true)
            ->doDeclareExchange($this->doDeclareExchanges)
        ;
    }

    /**
     * Create task worker.
     *
     * Worker will do ack/reject calls, and work on a queue bound to given
     * bouding keys.
     *
     * Queue name is optional, but if you need to setup multiple consumers
     * for processing in parallel the same type of message while ensuring they
     * are consumed only once, you must set them all on the same queue, no
     * matter what the bounding keys are.
     */
    public function createTaskWorker(string $exchange, array $bindingKeys, ?string $queueName = null): Consumer
    {
        return $this
            ->createConsumer($exchange)
            ->withAck(true)
            ->bindingKeys($bindingKeys)
            ->queue($queueName)
            ->exchangeType(self::EXCHANGE_DIRECT)
            ->exchangeIsDurable(true)
            ->doDeclareExchange($this->doDeclareExchanges)
        ;
    }

    /**
     * Create publish/subscribe-like publisher.
     *
     * It must be plugged onto a fanout exchange.
     *
     * It's probably a bad idea to use fanout exchanges, topic exchanges will
     * allow you to have a much more powerful and flexible routing scheme.
     */
    public function createFanoutPublisher(string $exchange, ?array $bindingKeys = null): Publisher
    {
        return $this
            ->createPublisher($exchange)
            ->exchangeType(self::EXCHANGE_FANOUT)
            ->exchangeIsDurable(false)
            ->doDeclareExchange($this->doDeclareExchanges)
        ;
    }

    /**
     * Create publish/subscribe-like subscriber.
     *
     * It must be plugged onto a fanout exchange.
     *
     * It's probably a bad idea to use fanout exchanges, topic exchanges will
     * allow you to have a much more powerful and flexible routing scheme.
     */
    public function createFanoutSubscriber(string $exchange): Consumer
    {
        return $this
            ->createConsumer($exchange)
            ->withAck(false)
            ->exchangeType(self::EXCHANGE_FANOUT)
            ->exchangeIsDurable(false)
            ->doDeclareExchange($this->doDeclareExchanges)
        ;
    }
}
