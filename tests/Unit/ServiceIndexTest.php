<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ServiceIndex;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * @mago-expect lint:too-many-methods
 */
final class ServiceIndexTest extends TestCase
{
    public function testResolvesTheShapesDrupalWrites(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'plain' => ['class' => 'Drupal\Core\Plain'],
            'leading_slash' => ['class' => '\Drupal\Core\Slashed'],
            'shorthand' => '@plain',
            'keyed' => ['alias' => 'plain'],
            'keyed_with_at' => ['alias' => '@plain'],
            'chained' => '@shorthand',
            'base' => ['abstract' => true, 'class' => 'Drupal\Core\Base'],
            'child' => ['parent' => 'base'],
            'grandchild' => ['parent' => 'child', 'arguments' => ['@plain']],
            'Drupal\Core\AsId' => [],
            'Drupal\Core\AsIdInterface' => '@plain',
        ]);

        self::assertSame('Drupal\Core\Plain', $index->get('plain')?->class);
        self::assertSame('Drupal\Core\Slashed', $index->get('leading_slash')?->class);
        self::assertSame('Drupal\Core\Plain', $index->get('shorthand')?->class);
        self::assertSame('Drupal\Core\Plain', $index->get('keyed')?->class);
        self::assertSame('Drupal\Core\Plain', $index->get('keyed_with_at')?->class);
        self::assertSame('Drupal\Core\Plain', $index->get('chained')?->class);
        self::assertSame('Drupal\Core\Base', $index->get('child')?->class);
        self::assertSame('Drupal\Core\Base', $index->get('grandchild')?->class);
        self::assertSame('Drupal\Core\AsId', $index->get('Drupal\Core\AsId')?->class);
        self::assertSame('Drupal\Core\Plain', $index->get('Drupal\Core\AsIdInterface')?->class);

        // The alias keeps its own id so a caller sees what it wrote.
        self::assertSame('shorthand', $index->get('shorthand')?->id);
    }

    public function testKeepsIdsWhoseClassIsUnknown(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'factory_only' => ['factory' => ['@other', 'make'], 'deprecated' => 'Gone.'],
            'placeholder' => ['class' => '%placeholder.class%'],
            'no_class_no_namespace' => ['arguments' => []],
            'parent_loop' => ['parent' => 'parent_loop'],
            'parent_missing' => ['parent' => 'missing'],
        ]);

        foreach (['factory_only', 'placeholder', 'no_class_no_namespace', 'parent_loop', 'parent_missing'] as $id) {
            self::assertTrue($index->has($id), $id);
            self::assertNull($index->get($id)?->class, $id);
        }

        self::assertSame('Gone.', $index->get('factory_only')?->deprecation);
    }

    public function testLeavesOutWhatTheContainerCannotHandBack(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'base' => ['abstract' => true, 'class' => 'Drupal\Core\Base'],
            'dangling' => '@missing',
            'dangling_keyed' => ['alias' => 'missing'],
            'empty_alias' => '@',
            'self_alias' => '@self_alias',
            'ping' => '@pong',
            'pong' => '@ping',
        ]);

        foreach ([
            'base',
            'dangling',
            'dangling_keyed',
            'empty_alias',
            'self_alias',
            'ping',
            'pong',
            'never_defined',
        ] as $id) {
            self::assertNull($index->get($id), $id);
            self::assertFalse($index->has($id), $id);
        }
    }

    public function testReadsDeprecationsWithPlaceholdersFilled(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'plain' => ['class' => 'Drupal\Core\Plain'],
            'old' => [
                'alias' => 'plain',
                'deprecated' => [
                    'package' => 'drupal/core',
                    'version' => '11.0.0',
                    'message' => 'The "%service_id%" alias is deprecated. Use "%alias_id%" instead.',
                ],
            ],
            'legacy' => [
                'class' => 'Drupal\Core\Legacy',
                'deprecated' => 'The "%service_id%" service is deprecated.',
            ],
        ]);

        self::assertNull($index->get('plain')?->deprecation);
        // Both placeholders name the id the caller wrote, as in Symfony's
        // Definition and Alias.
        self::assertSame('The "old" alias is deprecated. Use "old" instead.', $index->get('old')?->deprecation);
        self::assertSame('The "legacy" service is deprecated.', $index->get('legacy')?->deprecation);
    }

    public function testAppliesDecorators(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'processor' => ['class' => 'Drupal\Core\Processor', 'deprecated' => 'Old.'],
            'first' => ['class' => 'Drupal\first\Processor', 'decorates' => 'processor'],
            'second' => [
                'class' => 'Drupal\second\Processor',
                'decorates' => 'processor',
                'decoration_priority' => 5,
                'decoration_inner_name' => 'second.wrapped',
            ],
            'orphan' => ['class' => 'Drupal\orphan\Decorator', 'decorates' => 'missing'],
            'ignored' => [
                'class' => 'Drupal\ignored\Decorator',
                'decorates' => 'missing',
                'decoration_on_invalid' => 'ignore',
            ],
        ]);

        // The highest priority is applied first and wraps the original. The
        // lowest one wraps outermost, so it is what the id returns.
        self::assertSame('Drupal\first\Processor', $index->get('processor')?->class);
        self::assertSame('Old.', $index->get('processor')?->deprecation);
        self::assertSame('Drupal\second\Processor', $index->get('first.inner')?->class);
        self::assertSame('Drupal\Core\Processor', $index->get('second.wrapped')?->class);
        self::assertFalse($index->has('second.inner'));
        self::assertSame('Drupal\orphan\Decorator', $index->get('orphan')?->class);
        self::assertFalse($index->has('ignored'));
        // The class the id had stays available, for a run that does not
        // analyze the module the decorator comes from.
        self::assertSame('Drupal\Core\Processor', $index->get('processor')?->undecoratedClass);
        self::assertNull($index->get('first.inner')?->undecoratedClass);
        self::assertNull($index->get('orphan')?->undecoratedClass);
    }

    public function testStacksDecoratorsOfEqualPriorityInDefinitionOrder(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'repository' => ['class' => 'Drupal\Core\Repository'],
            'a' => ['class' => 'Drupal\a\Repository', 'decorates' => 'repository'],
            'b' => ['class' => 'Drupal\b\Repository', 'decorates' => 'repository'],
        ]);

        // The last one defined is applied last, so it is what the id returns.
        self::assertSame('Drupal\b\Repository', $index->get('repository')?->class);
        self::assertSame('Drupal\a\Repository', $index->get('b.inner')?->class);
        self::assertSame('Drupal\Core\Repository', $index->get('a.inner')?->class);
        // The `.inner` holding the definition is private; the one holding an
        // alias to the first decorator stays gettable, as every alias does.
        self::assertFalse($index->get('a.inner')?->public);
        self::assertTrue($index->get('b.inner')?->public);
    }

    public function testKnowsWhatTheContainerKeepsPrivate(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'hidden' => ['class' => 'Drupal\Core\Hidden', 'public' => false],
            'hidden.alias' => '@hidden',
            'base' => ['abstract' => true, 'class' => 'Drupal\Core\Base', 'public' => false],
            'child' => ['parent' => 'base'],
            'open_child' => ['parent' => 'base', 'public' => true],
            'password' => ['class' => 'Drupal\Core\Password'],
            'phpass.password' => [
                'class' => 'Drupal\phpass\Password',
                'public' => false,
                'decorates' => 'password',
            ],
        ]);

        self::assertFalse($index->get('hidden')?->public);
        // Drupal keeps every alias gettable, and with it the service behind.
        self::assertTrue($index->get('hidden.alias')?->public);
        // A child inherits its parent's privacy unless it sets its own.
        self::assertFalse($index->get('child')?->public);
        self::assertTrue($index->get('open_child')?->public);
        // A private decorator takes over a public id, which stays public.
        self::assertTrue($index->get('password')?->public);
        self::assertSame('Drupal\phpass\Password', $index->get('password')?->class);
        self::assertFalse($index->get('phpass.password')?->public);
        self::assertFalse($index->get('phpass.password.inner')?->public);
    }

    public function testFileDefaultsMakeServicesPrivate(): void
    {
        $index = ServiceIndex::fromFiles([dirname(__DIR__) . '/fixtures/services/private-defaults.services.yml']);

        self::assertFalse($index->get('hidden')?->public);
        self::assertTrue($index->get('shown')?->public);
        self::assertTrue($index->get('shown.alias')?->public);
    }

    public function testAliasesFollowTheDecorator(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'manager' => ['class' => 'Drupal\Core\Manager'],
            'Drupal\Core\ManagerInterface' => '@manager',
            'keyed' => ['alias' => 'manager'],
            'decorator' => ['class' => 'Drupal\contrib\Manager', 'decorates' => 'manager'],
        ]);

        foreach (['manager', 'Drupal\Core\ManagerInterface', 'keyed'] as $id) {
            self::assertSame('Drupal\contrib\Manager', $index->get($id)?->class, $id);
            self::assertSame('Drupal\Core\Manager', $index->get($id)?->undecoratedClass, $id);
        }
    }

    public function testDecoratingAnAliasLeavesItsTargetAlone(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'target' => ['class' => 'Drupal\Core\Target'],
            'target.alias' => '@target',
            'decorator' => ['class' => 'Drupal\contrib\Target', 'decorates' => 'target.alias'],
        ]);

        self::assertSame('Drupal\contrib\Target', $index->get('target.alias')?->class);
        self::assertSame('Drupal\Core\Target', $index->get('target')?->class);
        self::assertNull($index->get('target')?->undecoratedClass);
        self::assertSame('Drupal\Core\Target', $index->get('decorator.inner')?->class);
    }

    public function testDecoratorWithNullOnInvalidTakesOverAMissingId(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'decorator' => [
                'class' => 'Drupal\contrib\Decorator',
                'decorates' => 'missing',
                'decoration_on_invalid' => null,
            ],
        ]);

        self::assertSame('Drupal\contrib\Decorator', $index->get('missing')?->class);
        self::assertSame('Drupal\contrib\Decorator', $index->get('decorator')?->class);
        self::assertFalse($index->has('decorator.inner'));
    }

    public function testChildrenResolveTheWayTheCompilerDoes(): void
    {
        $index = ServiceIndex::fromDefinitions([
            'base' => [
                'abstract' => true,
                'class' => 'Drupal\Core\Base',
                'deprecated' => 'The "%service_id%" service is deprecated.',
            ],
            'child' => ['parent' => 'base'],
            'own_deprecation' => ['parent' => 'base', 'deprecated' => 'Own "%service_id%".'],
            'Drupal\contrib\Child' => ['parent' => 'base'],
            'concrete' => ['class' => 'Drupal\Core\Concrete'],
            'concrete.alias' => '@concrete',
            'through_alias' => ['parent' => 'concrete.alias'],
        ]);

        // An id that names a class is the class, even with a parent.
        self::assertSame('Drupal\contrib\Child', $index->get('Drupal\contrib\Child')?->class);
        // A parent that is an alias resolves through it.
        self::assertSame('Drupal\Core\Concrete', $index->get('through_alias')?->class);
        // A child inherits its parent's deprecation unless it has its own.
        self::assertSame('The "child" service is deprecated.', $index->get('child')?->deprecation);
        self::assertSame('Own "own_deprecation".', $index->get('own_deprecation')?->deprecation);
        self::assertNull($index->get('through_alias')?->deprecation);
    }

    public function testSkipsCompilerDirectives(): void
    {
        $index = ServiceIndex::fromFiles([dirname(__DIR__) . '/fixtures/services/directives.services.yml']);

        self::assertFalse($index->has('_defaults'));
        self::assertFalse($index->has('_instanceof'));
        self::assertSame('Drupal\Core\Real', $index->get('real')?->class);
        // One real service plus the six kernel and bootstrap container ones.
        self::assertSame(7, $index->count());
    }

    public function testAddsTheSyntheticKernelServices(): void
    {
        $index = ServiceIndex::fromDefinitions([]);

        self::assertSame('Drupal\Core\DrupalKernelInterface', $index->get('kernel')?->class);
        self::assertSame('Composer\Autoload\ClassLoader', $index->get('class_loader')?->class);
        self::assertSame('Drupal\Core\DependencyInjection\Container', $index->get('service_container')?->class);
        self::assertSame(
            'Drupal\Core\DependencyInjection\Container',
            $index->get('Symfony\Component\DependencyInjection\ContainerInterface')?->class,
        );
        self::assertSame('Drupal\Core\Cache\CacheBackendInterface', $index->get('cache.container')?->class);
        self::assertTrue($index->has('cache_tags_provider.container'));
        self::assertSame(6, $index->count());
    }

    public function testYamlDefinitionsWinOverSyntheticOnes(): void
    {
        $index = ServiceIndex::fromDefinitions(['kernel' => ['class' => 'Drupal\Test\Kernel']]);

        self::assertSame('Drupal\Test\Kernel', $index->get('kernel')?->class);
    }

    public function testReadsFilesInOrderAndSurvivesBadOnes(): void
    {
        $fixtures = dirname(__DIR__) . '/fixtures/services';
        $index = ServiceIndex::fromFiles([
            $fixtures . '/core.services.yml',
            $fixtures . '/parameters-only.services.yml',
            $fixtures . '/broken.services.yml',
            $fixtures . '/null-section.services.yml',
            $fixtures . '/missing.services.yml',
            $fixtures . '/override.services.yml',
        ]);

        self::assertSame('Drupal\Core\Thing', $index->get('core.thing')?->class);
        self::assertSame('Drupal\Override\Replacement', $index->get('core.overridden')?->class);
        self::assertSame('Drupal\Core\Thing', $index->get('override.alias')?->class);
        self::assertSame('Drupal\Core\NullBody', $index->get('Drupal\Core\NullBody')?->class);
        self::assertFalse($index->has('_defaults'));
        self::assertFalse($index->has('broken.thing'));
    }
}
