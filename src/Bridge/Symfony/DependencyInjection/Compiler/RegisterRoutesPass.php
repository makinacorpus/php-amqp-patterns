<?php

declare(strict_types=1);

namespace MakinaCorpus\AMQP\Patterns\Bridge\Symfony\DependencyInjection\Compiler;

use MakinaCorpus\AMQP\Patterns\Routing\RouteProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Lookup for all route providers and register routes.
 */
final class RegisterRoutesPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $routes = [];
        $routes += $this->getRoutesFromRouteProviders($container);
        $routes += $this->getRoutesFromYamlFiles($container);

        if ($routes) {
            $container->getDefinition('amqp_patterns.route_map.default')->setArgument(0, $routes);
        }
    }

    /**
     * Get routes from registered yaml definition files.
     */
    private function getRoutesFromYamlFiles(ContainerBuilder $container): array
    {
        $routes = [];
        if (!$container->hasParameter('amqp_patterns.route_map.files')) {
            return $routes;
        }

        $files = $container->getParameter('amqp_patterns.route_map.files');
        foreach ($files as $filename) {
            // Attempt smart resolve: if variable starts with "%" or "/" this
            // means that the developer already took care of making the path
            // absolute or discoverable, either by an absolute path, or by a
            // variable prefix.
            if ("%" !== $filename[0] && '/' !== $filename[0]) {
                $filename = "%kernel.project_dir%/".$filename;
            }
            $filename = $container->getParameterBag()->resolveValue($filename);

            if (!\file_exists($filename)) {
                throw new RuntimeException(\sprintf('Invalid filename "%s": file does not exist.', $filename));
            }

            $currentRoutes = Yaml::parseFile($filename);
            foreach ($currentRoutes as $type => $data) {
                // Validation is primitive, but more than enough.
                if (!\is_array($data)) {
                    throw new RuntimeException(\sprintf('Malformed file "%s": "%s" route must be an array.', $filename, $type));
                }
                foreach ($data as $value) {
                    if (!\is_scalar($value) || \is_array($value)) {
                        throw new RuntimeException(\sprintf('Malformed file "%s": "%s" route cannot contain sub-arrays.', $filename, $type));
                    }
                }
                $routes[$type] = $data;
            }
        }

        return $routes;
    }

    /**
     * Get routes from providers.
     */
    private function getRoutesFromRouteProviders(ContainerBuilder $container): array
    {
        $routes = [];
        foreach ($container->findTaggedServiceIds('amqp_patterns.route_provider') as $serviceId => $tags) {
            $class = $container->getDefinition($serviceId)->getClass();
            $ref = $container->getReflectionClass($class);

            if (null === $ref) {
                throw new RuntimeException(\sprintf('Invalid service "%s": class "%s" does not exist.', $serviceId, $class));
            }
            if (!$ref->implementsInterface(RouteProvider::class)) {
                throw new RuntimeException(\sprintf('Invalid service "%s": class "%s" must implement %s.', $serviceId, $class, RouteProvider::class));
            }

            $routes += \call_user_func([$class, 'defineRoutes']);
        }

        return $routes;
    }
}
