<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\AnalysisMemo;
use amateescu\MagoDrupal\Internal\ClassNames;
use Mago\Sdk\Analyzer\CallableSignatureOverride;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

use function count;
use function in_array;
use function ltrim;
use function preg_match;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

/**
 * Checks the arguments `getForm()` hands on to the form's `buildForm()`.
 *
 * `FormBuilderInterface::getForm($form_arg, mixed ...$args)` calls the
 * form's `buildForm($form, $form_state, ...$args)`, named arguments by name.
 * When the first argument names a form class, the call takes `$form_arg`
 * followed by that `buildForm()`'s parameters after the first two, so Mago
 * checks the count and the types as it does for a direct call.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class FormArgumentsProvider implements MethodReturnTypeProvider, CallableSignatureOverride
{
    private const FORM_INTERFACE = 'Drupal\Core\Form\FormInterface';

    /**
     * `Bar::class`, `Foo\Bar::class` or `\Foo\Bar::class`.
     */
    private const CLASS_CONSTANT = '/^(\\\\?[A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)*)\s*::\s*class$/i';

    /**
     * A single-quoted class name, where `\\` is one backslash.
     */
    private const CLASS_STRING = '/^\'((?:\\\\{1,2})?[A-Za-z_]\w*(?:\\\\{1,2}[A-Za-z_]\w*)*)\'$/';

    /**
     * Names in a `::class` fetch that stand for the calling class.
     */
    private const KEYWORDS = ['self', 'static', 'parent'];

    /**
     * The scanned class-likes, keyed by lowercased short name.
     *
     * @var AnalysisMemo<array<string, list<string>>>
     */
    private readonly AnalysisMemo $classes;

    public function __construct()
    {
        $this->classes = new AnalysisMemo();
    }

    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\Form\FormBuilderInterface', 'getForm')];
    }

    /**
     * The return type stays the declared one.
     */
    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return null;
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $invocation = $context->invocation;
        $argument = $invocation->getArgument(0, 'form_arg');
        if ($argument === null || !self::plain($invocation)) {
            return null;
        }

        $class = $this->formClass($context->codebase, trim($argument->expression));
        $method = $class === null ? null : $context->codebase->getDeclaringMethod($class, 'buildForm');
        if ($method === null || $method->abstract) {
            return null;
        }

        $parameters = self::parameters($method);

        // Mago's reports name the form's method, which the parameters and
        // their names come from.
        return $parameters === null
            ? null
            : new EffectiveCallableSignature($parameters, displayName: "{$class}::buildForm() via getForm()");
    }

    /**
     * Whether every argument is a plain one, neither unpacked nor a
     * placeholder, so the arguments line up with the parameters.
     */
    private static function plain(Invocation $invocation): bool
    {
        foreach ($invocation->arguments as $argument) {
            if ($argument->unpacked || $argument->placeholder) {
                return false;
            }
        }

        return true;
    }

    /**
     * The form class the argument text names, or null when it names none
     * this provider can be sure of.
     */
    private function formClass(Codebase $codebase, string $expression): ?string
    {
        $name = $this->className($codebase, $expression);
        $class = $name === null ? null : $codebase->getClass($name);
        if (
            $class === null
            || $class->kind !== ClassLikeKind::Class_
            || $class->flags->contains(MetadataFlags::ABSTRACT)
            || $class->hasIncompleteHierarchy()
            || !in_array(strtolower(self::FORM_INTERFACE), $class->parentInterfaces, strict: true)
        ) {
            return null;
        }

        return $class->originalName;
    }

    /**
     * The fully qualified class name a class name string or a `::class`
     * fetch gives, or null.
     *
     * Mago asks for the signature before it analyzes the arguments, so the
     * class is read off the argument's text. The request carries no file,
     * and the answer is kept for every call with the same text, so the
     * file's imports cannot be read. A string or a `\Foo\Bar::class` names
     * the class in full, and a relative `Bar::class` is looked up by name.
     * An import alias named like another class, `use Foo\A as B;` with a
     * scanned form `B` elsewhere, gets that form's parameters.
     */
    private function className(Codebase $codebase, string $expression): ?string
    {
        $matches = [];
        if (preg_match(self::CLASS_STRING, $expression, $matches) === 1) {
            return ltrim(str_replace(search: '\\\\', replace: '\\', subject: $matches[1]), characters: '\\');
        }

        if (
            preg_match(self::CLASS_CONSTANT, $expression, $matches) !== 1
            || in_array(strtolower($matches[1]), self::KEYWORDS, strict: true)
        ) {
            return null;
        }

        $name = $matches[1];

        return str_starts_with($name, '\\') ? substr($name, offset: 1) : $this->relative($codebase, $name);
    }

    /**
     * The one scanned class-like whose name ends in the relative name, or
     * null when there are none or several. Any class-like counts, so a name
     * a file imports stays quiet when another class shares it, even one that
     * is no form. An import alias cannot be seen, see className().
     */
    private function relative(Codebase $codebase, string $name): ?string
    {
        $classes = $this->classes->get($codebase, 'classes', static function () use ($codebase): array {
            $classes = [];
            foreach ($codebase->getClassLikeNames() as $class) {
                $classes[strtolower(ClassNames::short($class))][] = $class;
            }

            return $classes;
        });
        $suffix = '\\' . strtolower($name);
        $found = [];
        foreach ($classes[strtolower(ClassNames::short($name))] ?? [] as $class) {
            if (!str_ends_with('\\' . strtolower($class), $suffix)) {
                continue;
            }

            $found[] = $class;
        }

        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * `$form_arg` and the parameters of `buildForm()` after the first two,
     * or null when there are none or one of them is named `$form_arg`.
     *
     * A form that takes nothing after the form state reads what it is
     * passed from `$form_state->getBuildInfo()['args']`, if at all, and
     * alter hooks can read it there too, so any number of arguments is fine.
     *
     * An argument goes through `getForm()`'s variadic, so it is passed by
     * value whatever `buildForm()` declares. A default counts only where
     * every later parameter has one too, since PHP makes the others
     * required, and the SDK takes no required parameter after an optional
     * one.
     *
     * @return non-empty-list<CallableParameter>|null
     */
    private static function parameters(FunctionLikeMetadata $method): ?array
    {
        $declared = $method->parameters;
        $count = count($declared);
        if ($count <= 2) {
            return null;
        }

        $optional = [];
        $required = false;
        for ($position = $count - 1; $position >= 2; $position--) {
            $flags = $declared[$position]->flags;
            $optional[$position] =
                !$required
                && ($flags->contains(MetadataFlags::HAS_DEFAULT) || $flags->contains(MetadataFlags::VARIADIC));
            $required = $required || !$optional[$position];
        }

        $parameters = [new CallableParameter('$form_arg')];
        for ($position = 2; $position < $count; $position++) {
            $parameter = $declared[$position];
            if ($parameter->name === '$form_arg') {
                return null;
            }

            $variadic = $parameter->flags->contains(MetadataFlags::VARIADIC);
            $parameters[] = new CallableParameter(
                name: $parameter->name,
                type: $method->templates === [] ? $parameter->type->type ?? $parameter->declaredType?->type : null,
                variadic: $variadic,
                hasDefault: $optional[$position] && !$variadic,
            );
        }

        return $parameters;
    }
}
