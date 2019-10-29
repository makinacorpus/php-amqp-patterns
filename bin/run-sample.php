<?php

declare(strict_types=1);

use MakinaCorpus\AMQP\Patterns\PatternFactory;
use MakinaCorpus\AMQP\Patterns\Sample\Env;
use MakinaCorpus\AMQP\Patterns\Sample\FanoutPublisherSampleCommand;
use MakinaCorpus\AMQP\Patterns\Sample\FanoutSubscriberSampleCommand;
use MakinaCorpus\AMQP\Patterns\Sample\TaskPublisherSampleCommand;
use MakinaCorpus\AMQP\Patterns\Sample\TaskWorkerSampleCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';

// In most case, CLI will never be configured with a timeout, but you never now.
set_time_limit(0);

if (!class_exists(Dotenv::class)) {
    throw new \RuntimeException('You need to add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
}
foreach (['.env', '.env.dist'] as $candidate) {
    $filename = dirname(__DIR__).'/'.$candidate;
    if (file_exists($filename)) {
        (new Dotenv())->load($filename);
        break;
    }
}

$host = [];
if ($value = \getenv(Env::SERVER_HOST)) {
    $host['host'] = $value;
}
if ($value = \getenv(Env::SERVER_PASSWORD)) {
    $host['password'] = $value;
}
if ($value = \getenv(Env::SERVER_PORT)) {
    $host['port'] = $value;
}
if ($value = \getenv(Env::SERVER_USER)) {
    $host['user'] = $value;
}

$factory = new PatternFactory([$host]);

$application = new Application("AMQP patterns samples");
$application->add(new FanoutPublisherSampleCommand($factory));
$application->add(new FanoutSubscriberSampleCommand($factory));
$application->add(new TaskPublisherSampleCommand($factory));
$application->add(new TaskWorkerSampleCommand($factory));

$application->run();
