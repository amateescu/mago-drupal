<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\CallStyle;
use amateescu\MagoDrupal\Internal\ElementCallback;
use amateescu\MagoDrupal\Internal\ElementCallbacks;
use amateescu\MagoDrupal\Internal\ModuleFunctions;
use amateescu\MagoDrupal\Internal\ServiceIndex;
use amateescu\MagoDrupal\Internal\TestFiles;
use amateescu\MagoDrupal\Internal\TrustedCallbacks;
use amateescu\MagoDrupal\Internal\Types;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MethodFields;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\AtomicType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringVariant;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\Visibility;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function in_array;
use function preg_match;
use function strtolower;

/**
 * Reports render and form array callbacks that cannot run.
 *
 * Mago targets node kinds, not array keys, so this hook takes the whole
 * file: a regex over the file text looks for a quoted callback key first,
 * and only a file with one has its syntax fetched from the host. Callbacks
 * are read by ElementCallbacks; the class and method they name come from
 * the codebase.
 *
 * `static::class`, `$this` and an object stand for a class that may be a
 * subclass at run time. For those the class and every descendant in the
 * codebase are asked, and a single one with the method, or one that
 * trusts it, keeps the callback quiet.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ElementCallbackHook implements NodeAnalysisHook
{
    /**
     * Reported as `drupal/unknown-callback`, and so on, once the host adds
     * the plugin prefix.
     */
    public const UNKNOWN = 'unknown-callback';

    public const NON_STATIC = 'non-static-callback';

    public const NON_PUBLIC = 'non-public-callback';

    public const UNTRUSTED = 'untrusted-callback';

    private const FORM = 'Drupal\Core\Form\FormInterface';

    private const TRUSTED_LINK = 'https://www.drupal.org/node/2966725';

    /**
     * The attributes decide trust, and the details hold the visibility and
     * whether the method is static.
     */
    private const METHOD_FIELDS = MethodFields::ATTRIBUTES | MethodFields::METHOD_DETAILS;

    /**
     * @param Closure(Codebase): array<string, string> $modules Module machine
     *   name to directory.
     * @param Closure(Codebase): ServiceIndex $services Returns the current
     *   service index.
     */
    public function __construct(
        private readonly Closure $modules,
        private readonly Closure $services,
        private readonly TrustedCallbacks $trusted,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::Program];
    }

    public function getRequirements(): array
    {
        // Mago ships a file's text once for all hooks that ask for it. The
        // class-level hooks ask for it too, so only a file without a class
        // pays for it here.
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        // Tests build broken callbacks on purpose to exercise core's errors,
        // and hook documentation uses made-up names.
        if (
            preg_match(ElementCallbacks::GATE, $context->source->contents) !== 1
            || TestFiles::isTestOrHookDocumentation($context->analysis->file)
        ) {
            return;
        }

        foreach (ElementCallbacks::in($context->analysis->getSourceFile()) as $callback) {
            match ($callback->style) {
                CallStyle::Function => $this->checkFunction($context, $callback),
                CallStyle::FormObject => $this->checkFormObject($context, $callback),
                default => $this->checkMethod($context, $callback),
            };
        }
    }

    /**
     * A plain function name, reported only when the function would be in
     * the codebase if it existed. See ModuleFunctions.
     */
    private function checkFunction(NodeAnalysisContext $context, ElementCallback $callback): void
    {
        if (!$callback->use->checksFunctions()) {
            return;
        }

        // The callable resolver runs a service id through the service's
        // __invoke().
        $codebase = $context->codebase;
        $module = ModuleFunctions::owner(($this->modules)($codebase), $context->analysis->file, $callback->name);
        if (
            $module === null
            || $codebase->functionExists($callback->name)
            || ModuleFunctions::declares($codebase, $module[1], $callback->name)
            || $callback->use->resolves() && ($this->services)($codebase)->has($callback->name)
        ) {
            return;
        }

        $this->report(
            $context,
            $callback,
            self::UNKNOWN,
            Issue::new(
                "The {$callback->key} callback {$callback->name}() is a function that does not exist.",
                $callback->value->span,
                'no such function',
            )->withHelp("Check the name for a typo, or declare the function in the {$module[0]} module."),
        );
    }

    /**
     * `'::method'`, checked against the form class it is written in on a
     * form key. In any other class, a hook or a form alter, the form object
     * is some other class. On any other key it names no class at all.
     */
    private function checkFormObject(NodeAnalysisContext $context, ElementCallback $callback): void
    {
        if (!$callback->use->hasFormObject()) {
            $this->report(
                $context,
                $callback,
                self::UNKNOWN,
                Issue::new(
                    "The {$callback->key} callback \"::{$callback->name}\" names no class. Only the form API callbacks turn \"::method\" into a method of the form object.",
                    $callback->value->span,
                    'no class to call',
                )->withHelp("Name the class, as in [static::class, '{$callback->name}'], or use a closure."),
            );

            return;
        }

        $codebase = $context->codebase;
        $class = $callback->class === null || $callback->inHook ? null : $codebase->getClassLike($callback->class);
        if (
            $class === null
            || $class->hasIncompleteHierarchy()
            || !in_array(strtolower(self::FORM), $class->parentInterfaces, strict: true)
        ) {
            return;
        }

        $method = self::method($codebase, $class->name, $callback->name);
        if ($method !== null) {
            if (
                self::hidden($codebase, $class, $method)
                && !self::descendantPublic($codebase, $class->name, $callback->name)
            ) {
                $this->reportNonPublic($context, $callback, $class, $method);
            }

            return;
        }

        if (self::magic($codebase, $class->name) || self::descendantHas($codebase, $class->name, $callback->name)) {
            return;
        }

        $this->report(
            $context,
            $callback,
            self::UNKNOWN,
            Issue::new(
                "The form {$class->originalName} has no method {$callback->name}(), so the {$callback->key} callback \"::{$callback->name}\" cannot run.",
                $callback->value->span,
                'no such method',
            )->withHelp('Check the name for a typo, or add the method to the form.'),
        );
    }

    /**
     * A callback naming a class and a method.
     */
    private function checkMethod(NodeAnalysisContext $context, ElementCallback $callback): void
    {
        $target = $this->target($context, $callback);
        if ($target === null) {
            return;
        }

        [$class, $late, $style] = $target;
        $codebase = $context->codebase;
        $method = self::method($codebase, $class->name, $callback->name);
        if ($method === null) {
            if (
                !self::magic($codebase, $class->name)
                && (!$late || !self::descendantHas($codebase, $class->name, $callback->name))
            ) {
                $this->report($context, $callback, self::UNKNOWN, self::missing($callback, $class));
            }

            return;
        }

        $this->checkCall($context, $callback, $target, $method);
    }

    /**
     * A callback naming a method the class has: whether core can reach it,
     * call it the way the callback is written and trust it.
     *
     * @param array{ClassLikeMetadata, bool, CallStyle} $target
     */
    private function checkCall(
        NodeAnalysisContext $context,
        ElementCallback $callback,
        array $target,
        MethodMetadataProjection $method,
    ): void {
        [$class, $late, $style] = $target;
        $codebase = $context->codebase;

        // A subclass may make the method public.
        if (
            self::hidden($codebase, $class, $method)
            && (!$late || !self::descendantPublic($codebase, $class->name, $callback->name))
        ) {
            $this->reportNonPublic($context, $callback, $class, $method);

            return;
        }

        // The callable resolver instantiates the class for a
        // `'Class::method'` string, so an instance method still runs there.
        if (
            $style->isStatic()
            && $method->static === false
            && ($style === CallStyle::ClassArray || !$callback->use->resolves())
        ) {
            $this->report($context, $callback, self::NON_STATIC, self::nonStatic($callback, $class));

            return;
        }

        $interface = $callback->use->trustedInterface();
        if (
            $callback->use->checksTrust()
            && $this->trusted->rejects($codebase, $class, $method, $callback->name, $interface)
            && (!$late || $this->trusted->rejectedByDescendants($codebase, $class, $callback->name, $interface))
        ) {
            $this->report($context, $callback, self::UNTRUSTED, self::untrusted($callback, $class));
        }
    }

    private static function missing(ElementCallback $callback, ClassLikeMetadata $class): Issue
    {
        return Issue::new(
            "{$class->originalName} has no method {$callback->name}(), so the {$callback->key} callback cannot run.",
            $callback->value->span,
            'no such method',
        )->withHelp('Check the class and the method name.');
    }

    private static function nonStatic(ElementCallback $callback, ClassLikeMetadata $class): Issue
    {
        return Issue::new(
            "{$class->originalName}::{$callback->name}() is not static, but the {$callback->key} callback calls it statically.",
            $callback->value->span,
            'instance method called statically',
        )->withHelp(
            "Make the method static, or name an object in the callback, such as [\$this, '{$callback->name}'].",
        );
    }

    private static function untrusted(ElementCallback $callback, ClassLikeMetadata $class): Issue
    {
        return Issue::new(
            "Drupal does not trust {$class->originalName}::{$callback->name}() as a {$callback->key} callback and throws an UntrustedCallbackException.",
            $callback->value->span,
            'untrusted callback',
        )->withHelp(
            'Add #[TrustedCallback] to the method, or implement TrustedCallbackInterface and list the method in trustedCallbacks().',
        )->withLink(self::TRUSTED_LINK);
    }

    /**
     * Whether core cannot reach the method. Core calls every callback from
     * its own classes, which cannot call a protected or private method,
     * unless `__call()` or `__callStatic()` takes the call.
     */
    private static function hidden(Codebase $codebase, ClassLikeMetadata $class, MethodMetadataProjection $method): bool
    {
        return (
            $method->visibility !== null
            && $method->visibility !== Visibility::Public
            && !self::magic($codebase, $class->name)
        );
    }

    private function reportNonPublic(
        NodeAnalysisContext $context,
        ElementCallback $callback,
        ClassLikeMetadata $class,
        MethodMetadataProjection $method,
    ): void {
        $visibility = $method->visibility === Visibility::Private ? 'private' : 'protected';
        $this->report(
            $context,
            $callback,
            self::NON_PUBLIC,
            Issue::new(
                "{$class->originalName}::{$callback->name}() is {$visibility}, so the {$callback->key} callback cannot call it.",
                $callback->value->span,
                "{$visibility} method",
            )->withHelp('Make the method public. Core calls the callback from its own classes.'),
        );
    }

    /**
     * The class the callback names, with whether it may be a subclass at run
     * time and how PHP calls the method. Null for anything but a scanned
     * class with a complete hierarchy.
     *
     * @return array{ClassLikeMetadata, bool, CallStyle}|null
     */
    private function target(NodeAnalysisContext $context, ElementCallback $callback): ?array
    {
        $name = $callback->class;
        $late = $callback->late;
        $style = $callback->style;
        if ($callback->object !== null) {
            $type = $context->analysis->getExpressionType($callback->object);
            $typed = $type === null || count($type->atomicTypes) !== 1 ? null : self::typed($type->atomicTypes[0]);
            if ($typed === null) {
                return null;
            }

            [$name, $late, $style] = $typed;
        }

        $class = $name === null ? null : $context->codebase->getClassLike($name);
        if ($class === null || $class->kind !== ClassLikeKind::Class_ || $class->hasIncompleteHierarchy()) {
            return null;
        }

        return [$class, $late, $style];
    }

    /**
     * The one class an object or a class-string names, with whether it may
     * be a subclass at run time and how PHP calls the method. PHP calls a
     * class-string statically, as it calls `[Foo::class, 'method']`. A
     * literal one names that exact class; one of a type, such as a
     * parameter declared `class-string<Foo>`, may name a subclass.
     *
     * @return array{string, bool, CallStyle}|null
     */
    private static function typed(AtomicType $atomic): ?array
    {
        if ($atomic instanceof NamedObjectType) {
            $names = Types::names(Type::fromAtomic($atomic));

            return count($names) === 1 ? [$names[0], true, CallStyle::ObjectArray] : null;
        }

        $string = $atomic instanceof ScalarType ? $atomic->refinement : null;
        if (!$string instanceof ClassLikeStringType) {
            return null;
        }

        if ($string->variant === ClassLikeStringVariant::Literal) {
            return (
                $string->literal === null || $string->literal === ''
                    ? null
                    : [$string->literal, false, CallStyle::ClassArray]
            );
        }

        $constraint = $string->variant === ClassLikeStringVariant::OfType ? $string->constraint : null;
        $names = $constraint instanceof NamedObjectType ? Types::names(Type::fromAtomic($constraint)) : [];

        return count($names) === 1 ? [$names[0], true, CallStyle::ClassArray] : null;
    }

    /**
     * Whether the class takes any call through `__call()` or
     * `__callStatic()`.
     */
    private static function magic(Codebase $codebase, string $class): bool
    {
        return $codebase->findMethods(class: $class, name: '__call*', fields: 0) !== [];
    }

    /**
     * Whether a descendant of the class has the method, or takes any call
     * through `__call()` or `__callStatic()`.
     */
    private static function descendantHas(Codebase $codebase, string $class, string $name): bool
    {
        return (
            $codebase->findMethods(descendantsOf: $class, name: $name, fields: 0) !== []
            || $codebase->findMethods(descendantsOf: $class, name: '__call*', fields: 0) !== []
        );
    }

    /**
     * Whether a descendant of the class sees the method as public.
     */
    private static function descendantPublic(Codebase $codebase, string $class, string $name): bool
    {
        foreach ($codebase->findMethods(
            descendantsOf: $class,
            name: $name,
            fields: MethodFields::METHOD_DETAILS,
        ) as $method) {
            if ($method->visibility === Visibility::Public) {
                return true;
            }
        }

        return false;
    }

    private function report(NodeAnalysisContext $context, ElementCallback $callback, string $code, Issue $issue): void
    {
        $context->report($callback->use->level(), $code, $issue);
    }

    private static function method(Codebase $codebase, string $class, string $name): ?MethodMetadataProjection
    {
        return $codebase->findMethods(class: $class, name: $name, fields: self::METHOD_FIELDS)[0] ?? null;
    }
}
