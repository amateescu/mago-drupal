<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\Attributes;
use amateescu\MagoDrupal\Internal\DeprecationScopes;
use amateescu\MagoDrupal\Internal\FileMembers;
use amateescu\MagoDrupal\Internal\ServiceIndex;
use amateescu\MagoDrupal\Internal\TestFiles;
use amateescu\MagoDrupal\Internal\Types;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function in_array;
use function preg_replace;
use function str_contains;
use function strcspn;
use function strtolower;
use function substr;

/**
 * Reports `\Drupal::…` calls inside classes that can have services injected,
 * or static `load()` and `loadMultiple()` calls on entity classes there.
 *
 * Ports phpstan-drupal's GlobalDrupalDependencyInjectionRule, narrowed to
 * classes that can take services: descendants of ContainerInjectionInterface
 * and ContainerFactoryPluginInterface, entity handlers and container
 * derivers, whose factories get the container, classes registered as
 * services in a services file, hook classes, which Drupal registers as
 * autowired services, and BlockBase plugins, as Coder reports them.
 * Entities, typed data and stream wrappers are left alone. Static methods have no injected services to use instead
 * and are skipped too, and so are constructors with a parameter that accepts
 * null (see isFallback()). Deprecated code and the callables passed to
 * `DeprecationHelper::backwardsCompatibleCall()` are skipped as well: a
 * deprecated method keeps its lookup until it is removed, and a callable for
 * newer core can use a service older core does not have. The host hands over
 * the `\Drupal::` calls themselves, subclasses and instances of `Drupal`
 * included; the enclosing class and method come from their locations.
 *
 * The plugin registers a second instance for static entity loads, which
 * ports DrupalPractice's GlobalClass sniff. `Node::load()` looks up the
 * storage through `\Drupal` too, so the same classes and skips apply.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class GlobalDrupalCallHook implements MethodCallAnalysisHook
{
    public const CODE = 'global-drupal-call';

    public const ENTITY_LOAD_CODE = 'static-entity-load';

    /**
     * Declares the static `load()` and `loadMultiple()`. A target on it
     * matches every entity class.
     */
    private const ENTITY = 'Drupal\Core\Entity\EntityInterface';

    public const ANCESTORS = [
        'Drupal\Core\DependencyInjection\ContainerInjectionInterface',
        'Drupal\Core\Plugin\ContainerFactoryPluginInterface',
        'Drupal\Core\Entity\EntityHandlerInterface',
        'Drupal\Core\Plugin\Discovery\ContainerDeriverInterface',
    ];

    /**
     * The one base class of Coder's list that implements neither container
     * interface. A block plugin takes services once it adds `create()`.
     */
    private const BLOCK_BASE = 'drupal\core\block\blockbase';

    /**
     * @param Closure(Codebase): ServiceIndex $services Returns the current index.
     * @param bool $entityLoads Reports static entity loads instead of
     *   `\Drupal` calls.
     */
    public function __construct(
        private readonly Closure $services,
        private readonly bool $entityLoads = false,
    ) {}

    public function getTargets(): array
    {
        return (
            $this->entityLoads
                ? [MethodTarget::exact(self::ENTITY, 'load'), MethodTarget::exact(self::ENTITY, 'loadMultiple')]
                : [MethodTarget::allMethods('Drupal')]
        );
    }

    public function getRequirements(): array
    {
        // Mago ships a file's text once for all hooks, and other hooks
        // already ask for it.
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $file = $context->analysis->file;
        $contents = $context->source->contents;
        // PHP also runs a static method called on an object, and the host
        // hands those calls over too. A docblock that types a storage as its
        // entity class turns `$this->storage->load()` into one, so only the
        // `Node::load()` syntax counts as an entity load.
        if (
            $this->entityLoads && $context->node->kind !== NodeKind::StaticMethodCall
            || TestFiles::isTest($file)
            || DeprecationScopes::marked($contents, $file)
            && DeprecationScopes::of($contents, $file)->covers($context->node->span)
        ) {
            return;
        }

        $members = FileMembers::of($context->source);
        $class = $members->classAt($context->codebase, $context->source, $context->node->span);
        if ($class === null || !$this->canTakeServices($members, $context, $class)) {
            return;
        }

        $method = $members->methodAt($context->codebase, $class, $context->node->span);
        if ($method === null || $method->static === true || self::isFallback($method)) {
            return;
        }

        if ($this->entityLoads) {
            $context->report(Level::Warning, self::ENTITY_LOAD_CODE, self::entityLoad($context));

            return;
        }

        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new(
                '\Drupal static calls inside a class bypass dependency injection.',
                $context->node->span,
            )->withHelp(
                'Inject the service through the constructor, or through create() for forms, controllers and plugins.',
            ),
        );
    }

    /**
     * The issue for a static entity load, which names the call as written,
     * such as `Node::load()`.
     */
    private static function entityLoad(NodeAnalysisContext $context): Issue
    {
        $text = $context->source->getText($context->node->span);
        $callee = substr($text, offset: 0, length: strcspn($text, characters: '('));
        $callee = (string) preg_replace('/\s+/', replacement: '', subject: $callee);

        return Issue::new("{$callee}() inside a class bypasses dependency injection.", $context->node->span)->withHelp(
            'Inject the entity type manager, and load the entity through its getStorage().',
        );
    }

    /**
     * Whether the class can get services injected. The cheap tests on the
     * class's own metadata come first, and the services index last.
     */
    private function canTakeServices(FileMembers $members, NodeAnalysisContext $context, ClassLikeMetadata $class): bool
    {
        return (
            self::injectable($class->parentInterfaces)
            || in_array(self::BLOCK_BASE, $class->parentClasses, strict: true)
            || self::hookClass($members, $context, $class)
            || ($this->services)($context->codebase)->hasClass($class->originalName)
        );
    }

    /**
     * Whether the method is a constructor with a parameter that accepts null.
     * Drupal's deprecation policy adds a new service to a constructor as a
     * nullable parameter, and the body falls back to `\Drupal::service()`
     * for callers that do not pass it yet. That call keeps old callers
     * working, so it is not a missed injection.
     */
    private static function isFallback(MethodMetadataProjection $method): bool
    {
        if ($method->constructor !== true) {
            return false;
        }

        foreach ($method->parameters ?? [] as $parameter) {
            if (
                Types::includesNull($parameter->declaredType?->type)
                || Types::includesNull($parameter->defaultType?->type)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the class implements a hook through `#[Hook]`, on the class or
     * on a method it declares. Drupal registers such a class as an autowired
     * service, so its constructor can take any service. A class whose text
     * holds no attribute at all costs no method lookup.
     */
    private static function hookClass(
        FileMembers $members,
        NodeAnalysisContext $context,
        ClassLikeMetadata $class,
    ): bool {
        if (Attributes::named($class->attributes, Attributes::HOOK) !== []) {
            return true;
        }

        if (!str_contains($context->source->getText($class->location->span), '#[')) {
            return false;
        }

        foreach ($members->methods($context->codebase, $class) as $method) {
            if (Attributes::named($method->attributes ?? [], Attributes::HOOK) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $interfaces Lowercased.
     */
    private static function injectable(array $interfaces): bool
    {
        foreach (self::ANCESTORS as $ancestor) {
            if (in_array(strtolower($ancestor), $interfaces, strict: true)) {
                return true;
            }
        }

        return false;
    }
}
