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

## Publish/subscribe pattern with fanout exchange

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

$publisher = $factory->createPublisher('amqp_patterns_sample_pubsub');

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
        'content_type' => 'application/json',
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

For running samples, you need a working AMQP broker listening on localhost.

As of now, it was only tested using RabbitMQ 3.7.x.

## Publish/subscribe pattern with fanout exchange

Please read https://www.rabbitmq.com/tutorials/tutorial-three-php.html

This will work only on a `fanout` exchange type, it may or may be not already
declared in the broker.

Per default, exchange name is `amqp_patterns_sample_pubsub`, there is no queue
nor any kind of binding involved.

First step is to start one or more subscribers:

```sh
bin/run-sample amqp-pattern:sample:fanout-subscriber --exchange=my_exchange
```

Additionnaly, you can ask for the subscriber to display full message properties
upon receival:

```sh
bin/run-sample amqp-pattern:sample:fanout-subscriber \
    --exchange=my_fanout_exchange \
    --show-properties
```

Since this is a `fanout` exchange all started subscribers will receive all
messages, and when there is no subscribers, the broker will drop the messages.

Please be aware that the exchange, if non existing, will be created as a fanout
exchange, and set to durable: it will survive client deconnection and broker
restart.

Once you started all your subsribers, just send messages into the queue:

```sh
bin/run-sample amqp-pattern:sample:fanout-publisher \
    --exchange=my_fanout_exchange \
    --content-type="text/plain" \
    "Hello, world"
```

## Worker pattern with direct exchange

Please read https://www.rabbitmq.com/tutorials/tutorial-two-php.html

This will work only on a `direct` exchange type, it may or may be not already
declared in the broker.

Per default, exchange name is `amqp_patterns_sample_worker`, there is no queue
nor any kind of binding involved.

First step is to start one or more workers:

```sh
bin/run-sample amqp-pattern:sample:task-worker \
    --exchange=my_direct_exchange \
    --queue=my_task_queue
```

Since this is a `direct` exchange, and messages will be sent to queues, they
will be requeued if no consumer did `ack` it properly, until the message reaches
its lifetime or is consumed properly.

Please be aware that the exchange, if non existing, will be created as a direct
exchange, and set to durable: it will survive client deconnection and broker
restart.

Once you started all your subsribers, just send messages into the queue:

```sh
bin/run-sample amqp-pattern:sample:task-publisher \
    --exchange=my_direct_exchange \
    --content-type="text/plain" \
    --routing-key=my_task_queue \
    "Hello, world"
```

You will notice that even if you start more than one worker, each message
will only be consumed once by one and only one worker. 

Bonus, you can also test message requeue:

```sh
bin/run-sample amqp-pattern:sample:task-publisher \
    --exchange=my_direct_exchange \
    --content-type="text/plain" \
    --routing-key=my_task_queue \
    "reject"
```

Or force a reject without requeue:

```sh
bin/run-sample amqp-pattern:sample:task-publisher \
    --exchange=my_direct_exchange \
    --content-type="text/plain" \
    --routing-key=my_task_queue \
    "invalid"
```

Any other message value will trigger an ack.

## Worker pattern with topic exchange

Still needs to be done, sorry.
