<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns\Routing;

use MakinaCorpus\AMQP\Patterns\PatternFactory;

/**
 * Implement this interface in order to be able to provide AMQP routes.
 *
 * Routes are objects that define information about exchange and routing keys
 * for sending a message based upon a PHP class name or an alias.
 *
 * This allows, for example, to plug some level of auto-configuration over the
 * Symfony message bus, or to write business oriented packages that only contain
 * message value classes, along with their routing information within the target
 * environment, then share it within multiple PHP applications.
 */
interface RouteProvider
{
    /**
     * Define routes.
     *
     * Keys of the returned iterator must be PHP message class names or type
     * alias, values must be Route instances.
     */
    public static function defineRoutes(): iterable;
}

/**
 * Get route for publishing a message.
 *
 * In the target infrastructure, applications will exchange messages across
 * an AMQP compatible message bus. Some of these shared messages will be
 * bundled in interface packages, along with their addressing/routing
 * information.
 *
 * This interface hides a component that is supposed to aggregate all routing
 * information from various of those packages and other applications and such,
 * make the routing transparent for the developer which only need to dispatch
 * those messages via the Dispatcher instance.
 */
interface RouteMap
{
    /**
     * Get route for message type, can be a codified name or a class name.
     */
    public function getRouteFor(string $type): Route;
}

/**
 * Single routing information for a message.
 */
final class Route
{
    /** @var string */
    private $contentType = 'application/json';

    /** @var ?string */
    private $exchange = 'internal_messages';

    /** @var string */
    private $exchangeType = 'direct';

    /** @var bool */
    private $durable = false;

    /** @var bool */
    private $persistent = false;

    /** @var ?string */
    private $routingKey;

    /** @var ?string */
    private $hash;

    /**
     * Create route from array
     */
    public static function fromArray(array $values): self
    {
        $ret = new self();
        $ret->contentType = $values['content_type'] ?? 'application/json';
        $ret->durable = (bool)($values['durable'] ?? true); // Default is not the same.
        $ret->exchange = $values['exchange'] ?? null;
        $ret->exchangeType = $values['exchange_type'] ?? null;
        $ret->persistent = $values['persistent'] ?? false;
        $ret->routingKey = $values['routing_key'] ?? null;

        return $ret;
    }

    /**
     * Default empty route.
     *
     * It will route without any information, just sent across the bus as-is
     * and let the broker configuration do whatever it want with it.
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * Get target content type
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Get routing key, queue name, or null for fanout exchanges
     */
    public function getRoutingKey(): ?string
    {
        return $this->routingKey;
    }

    /**
     * Get exchange name
     */
    public function getExchange(): ?string
    {
        return $this->exchange;
    }

    /**
     * Get exchange type
     */
    public function getExchangeType(): string
    {
        return $this->exchangeType;
    }

    /**
     * Are message to be marked as persistent in this queue?
     */
    public function areMessagesPersistent(): bool
    {
        return $this->persistent;
    }

    /**
     * Is exchange durable
     */
    public function isExchangeDurable(): bool
    {
        return $this->durable;
    }

    /**
     * Get a hash for runtime caching
     */
    public function getHash()
    {
        return $this->hash ?? ($this->hash = \sha1($this->exchange ?? ''));
    }
}

/**
 * Default array-based implementation.
 */
final class DefaultRouteMap implements RouteMap
{
    /** @var mixed[][] */
    private $data = [];

    /** @var ?rount */
    private $defaultRoute;

    /**
     * Default constructor
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Set default exchange.
     */
    public function setDefaultExchange(string $exchange): void
    {
        $this->defaultRoute = Route::fromArray([
            'durable' => true,
            'exchange' => $exchange,
            'exchange_type' => PatternFactory::EXCHANGE_DIRECT,
            'routing_key' => 'internal_messages',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteFor(string $type): Route
    {
        if ($route = ($this->data[$type] ?? null)) {
            return Route::fromArray($route);
        }
        return $this->defaultRoute ?? Route::empty();
    }
}
