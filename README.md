# Implementation of some AMQP 9.1 well known patterns using php-amqplib

This package provide a minimalistic API that implements a few patterns that
can be built atop of AMQP 9.1. Most of those patterns are directly extracted
and refined from the official RabbitMQ documentation.

For more information, read https://www.rabbitmq.com/getstarted.html

This library requires `php-amqplib/php-amqplib` dependency, this is an
arbitrary choice but a reasonable one.

# Usage

## Create the factory and connection

A factory will maintain a single stream connexion. It may hold one or more
channel, that will be dynamically created or closed depending on how you
use it.

```php
<?php

declare(strict_types=1);

use MakinaCorpus\AMQP\Patterns\PatternFactory;

$factory = new PatternFactory();
```

Per default, it will attempt a connection on `amqp://guest:guest@127.0.0.1`
with no `vhost` on default RabbitMQ port `5672`.

If you need to define one or more servers, use the first constructor argument.
You cannot control how the connection will behave, per default it will
attempt to reach each server and stop on the first one accepting the connection.

```php
<?php

declare(strict_types=1);

use MakinaCorpus\AMQP\Patterns\PatternFactory;

$factory = new PatternFactory([
    [
        'host' => '10.3.1.1',
        'port' => '5672',
        'user' => 'guest',
        'password' => 'guest',
        'vhost' => null,
    ],
    [
        'host' => '10.3.2.2',
        // All parameters are optional, please note that they will be merged
        // with defaults if some are missing.
    ],
    // ...
]);
```

Additionnaly, you can randomise servers order by setting `true` to the second
constructor argument:


```php
<?php

declare(strict_types=1);

use MakinaCorpus\AMQP\Patterns\PatternFactory;

$factory = new PatternFactory([ /* ... */], true);
```

## Publish/subscribe pattern

Please read https://www.rabbitmq.com/tutorials/tutorial-three-php.html

This will work only on a `fanout` exchange type, it may or may be not already
declared in the broker.

### Publisher

Very simple usage:

```php
<?php

declare(strict_types=1);

use MakinaCorpus\AMQP\Patterns\PatternFactory;

$factory = new PatternFactory(/* ... */);

$publisher = $factory->createPublisher('user.message');

// Raw message without any properties
$publisher->publish('Some message');

// JSON message, with 'content-type' property
$publisher->publish(<<<JSON
{
    "from": "John Doe",
    "message": "Hello, Jane !"
}
JSON
    ,
    // Arbitrary AMQP valid message properties
    [
        'content_type': 'application/json',
    ]
);
```

### Subscriber

Very simple usage:

```php
<?php

declare(strict_types=1);

use MakinaCorpus\AMQP\Patterns\PatternFactory;

$factory = new PatternFactory(/* ... */);

$factory
    // This is the name of the exchange
    ->createSubscriber('user.message')
    // Callback is what your business will do upon message received
    ->callback(function (AMQPMessage $message) use ($output) {
        echo \sprintf("[%s] %s", (new \DateTime())->format('Y-m-d H:i:s'), $message->body));
    })
    // By calling this method, you enable graceful shutdown when process gets
    // killed (you need ext-pcntl to be enabled).
    ->registerSignalHandlers()
    // Run it.
    ->run()
;
```

# Running samples

A command is available as `bin/run-sample`, simply run it and try commands.

