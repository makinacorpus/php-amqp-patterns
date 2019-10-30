<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns\Bridge\Symfony;

use MakinaCorpus\AMQP\Patterns\Bridge\Symfony\DependencyInjection\Compiler\RegisterRoutesPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @codeCoverageIgnore
 */
final class AmqpPatternsBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new RegisterRoutesPass());
    }

    /**
     * {@inheritdoc}
     */
    protected function createContainerExtension()
    {
        return new class () extends Extension
        {
            public function load(array $configs, ContainerBuilder $container)
            {
                $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/Resources/config'));
                $loader->load('services.yaml');
            }

            public function getAlias()
            {
                return 'amqp_patterns';
            }

            public function getConfiguration(array $config, ContainerBuilder $container)
            {
                return new class () implements ConfigurationInterface // Null instance
                {
                    public function getConfigTreeBuilder()
                    {
                        return new TreeBuilder();
                    }
                };
            }
        };
    }
}
