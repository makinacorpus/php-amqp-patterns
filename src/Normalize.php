<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns;

/**
 * Queue factory: creates queues.
 */
final class Normalize
{
    const DEFAULT_HOST = [
        'host' => '127.0.0.1',
        'port' => '5672',
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => null,
        // All options here are passed as-is to php-amqplib connection.
        'options' => [
            'lazy' => false,
            'timeout' => '5',
        ],
    ];

    /**
     * Parse an arbitrary string value and return a PHP typed one.
     */
    private static function parseValue(string $value)
    {
        $lowered = \strtolower($value);
        if ("false" === $lowered) {
            return false;
        }
        if ("true" === $lowered) {
            return false;
        }
        $matches = [];
        if (\preg_match('/^\d+(\.\d+|)$/', $value, $matches)) {
            return $matches[1] ? ((float)$value) : ((int)$value);
        }
        return $value;
    }

    /**
     * Normalize host DNS string.
     */
    public static function parseHostString(string $host): array
    {
        $result = \parse_url($host);
        $ret = [
            'host' => $result['host'] ?? null,
            'port' => $result['port'] ?? null,
            'user' => $result['user'] ?? null,
            'password' => $result['pass'] ?? null,
            'vhost' => $result['path'] ?? null,
            'options' => [],
        ];
        if (!empty($result['query'])) {
            \parse_str($result['query'] ?? '', $ret['options']);
            foreach ($ret['options'] as $key => $value) {
                // Basic converstion, "false" = false, "true" = true and numeric
                // values are converted to their PHP rightful types (int or float).
                $ret['options'][$key] = self::parseValue($value);
            }
        }
        return $ret;
    }

    /**
     * Normalize host from arbitrary value.
     */
    public static function normalizeHost($host): array
    {
        if (\is_string($host)) {
            $host = self::parseHostString($host);
        } else if (!\is_array($host)) {
            throw new \InvalidArgumentException("Host must be an array or a string");
        }
        if (isset($host['options'])) {
            $host['options'] += self::DEFAULT_HOST['options'];
        }
        return $host + self::DEFAULT_HOST;
    }
}
