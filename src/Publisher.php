<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

/**
 * Common interface for publishers
 */
interface Publisher
{
    /**
     * Publish a message
     *
     * Data must have been already encoded if necessary
     */
    public function publish(string $data, array $properties = [], ?string $routingKey = null): void;
}
