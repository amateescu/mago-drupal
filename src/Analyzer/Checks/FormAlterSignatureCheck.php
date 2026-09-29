<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\ParameterMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\AnyObjectType;
use Mago\Sdk\Analyzer\Type\MixedType;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;

use function implode;
use function preg_match;

/**
 * Checks `#[Hook('form_alter')]` and `form_FORM_ID_alter` method signatures.
 *
 * Ports phpstan-drupal's HookFormAlterRule. The module handler calls every
 * variant as `(array &$form, FormStateInterface $form_state, string $form_id)`
 * and trailing parameters may be left off; a required fourth one breaks the
 * call. Types are checked only where the method declares them, and `mixed`,
 * or `object` for the form state, takes what the module handler passes.
 * ProceduralHookHook runs the same check on procedural implementations.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class FormAlterSignatureCheck implements MetadataCheck
{
    public const CODE = 'hook-form-alter-signature';

    private const FORM_STATE = ['FormStateInterface', 'FormState'];

    public function textGate(): ?string
    {
        return null;
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        foreach (HookMethods::of($class) as [$hook, $method, $location]) {
            if (!self::isFormAlter($hook)) {
                continue;
            }

            $problems = self::problems(HookMethods::parameters($method));
            if ($problems !== []) {
                $reporter->error(self::CODE, self::issue(HookMethods::label($method), $hook, $problems, $location));
            }
        }
    }

    /**
     * Whether the hook is `hook_form_alter` or one of its form ID variants.
     */
    public static function isFormAlter(string $hook): bool
    {
        return preg_match('/^form(_[A-Za-z0-9_]+)?_alter$/', $hook) === 1;
    }

    /**
     * Everything wrong with one alter implementation's parameter list.
     *
     * @param list<ParameterMetadata> $parameters
     *
     * @return list<string>
     */
    public static function problems(array $parameters): array
    {
        $problems = [];
        $fourth = $parameters[3] ?? null;
        if (
            $fourth !== null
            && !$fourth->flags->contains(MetadataFlags::HAS_DEFAULT)
            && !$fourth->flags->contains(MetadataFlags::VARIADIC)
        ) {
            $problems[] = 'it requires more than the three arguments the module handler passes';
        }

        $form = $parameters[0] ?? null;
        if ($form !== null && !$form->flags->contains(MetadataFlags::BY_REFERENCE)) {
            $problems[] = 'the first parameter must be taken by reference';
        }

        $formType = $form?->declaredType?->type;
        if ($formType !== null && !Types::isArray($formType) && !self::wide($formType)) {
            $problems[] = 'the first parameter must be an array';
        }

        $formState = $parameters[1] ?? null;
        $formStateType = $formState?->declaredType?->type;
        if (
            $formStateType !== null
            && !HookMethods::typed($formState, self::FORM_STATE)
            && !self::wide($formStateType)
            && !self::anyObject($formStateType)
        ) {
            $problems[] = 'the second parameter must be a FormStateInterface';
        }

        $formId = $parameters[2] ?? null;
        $formIdType = $formId?->declaredType?->type;
        if ($formIdType !== null && !Types::isString($formIdType) && !self::wide($formIdType)) {
            $problems[] = 'the third parameter must be a string';
        }

        return $problems;
    }

    /**
     * Whether the type takes any value, so the module handler's argument fits.
     */
    private static function wide(Type $type): bool
    {
        foreach ($type->atomicTypes as $atomic) {
            if ($atomic instanceof MixedType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the type takes any object, which is enough for the form state.
     */
    private static function anyObject(Type $type): bool
    {
        foreach ($type->atomicTypes as $atomic) {
            if ($atomic instanceof AnyObjectType) {
                return true;
            }
        }

        return false;
    }

    /**
     * The issue for a function or method that implements a form alter hook
     * with the wrong signature.
     *
     * @param non-empty-list<string> $problems
     */
    public static function issue(string $name, string $hook, array $problems, Span|SourceLocation $where): Issue
    {
        return Reporter::issue(
            "{$name}() implements hook_{$hook} with the wrong signature: " . implode(', ', $problems) . '.',
            $where,
            'The module handler calls it as (array &$form, FormStateInterface $form_state, string $form_id); trailing parameters may be left off.',
        );
    }
}
