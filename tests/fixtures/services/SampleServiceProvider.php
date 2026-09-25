<?php

namespace Drupal\sample;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Registers services in PHP, one per shape the id scan reads.
 */
class SampleServiceProvider extends ServiceProviderBase
{
    public function register(ContainerBuilder $container) {
        $container->register('sample.plain', Plain::class);
        $container
            ->register("sample.chained")
            ->setClass(Chained::class);
        $container->setDefinition('sample.defined', new Definition($this->pick()));
        $container->setAlias('sample.alias', 'sample.plain');
        $container->register($this->dynamicId(), Dynamic::class);
    }
}
