<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;

use function implode;
use function preg_match;

/**
 * Checks `#[Hook('form_alter')]` and `form_FORM_ID_alter` method signatures.
 *
 * Ports phpstan-drupal's HookFormAlterRule. The module handler calls every
 * variant as `(array &$form, FormStateInterface $form_state, string $form_id)`
 * and trailing parameters may be left off; a required fourth one breaks the
 * call. Types are checked only where the method declares them.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class FormAlterSignatureCheck implements MetadataCheck
{
    public const CODE = 'hook-form-alter-signature';

    private const FORM_STATE = ['FormStateInterface', 'FormState'];

    public function mentionsAny(): array
    {
        return [];
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        foreach (HookMethods::of($class) as [$hook, $method]) {
            $location = $method->nameLocation ?? $method->location;
            if ($location === null || preg_match('/^form(_[A-Za-z0-9_]+)?_alter$/', $hook) !== 1) {
                continue;
            }

            $problems = self::problems($method);
            if ($problems === []) {
                continue;
            }

            $name = HookMethods::label($method);
            $reporter->error(self::CODE, Reporter::issue(
                "{$name}() implements hook_{$hook} with the wrong signature: " . implode(', ', $problems) . '.',
                $location,
                'The module handler calls it as (array &$form, FormStateInterface $form_state, string $form_id); trailing parameters may be left off.',
            ));
        }
    }

    /**
     * Everything wrong with one alter method's parameter list.
     *
     * @return list<string>
     */
    private static function problems(MethodMetadataProjection $method): array
    {
        $parameters = HookMethods::parameters($method);
        $problems = [];
        $fourth = $parameters[3] ?? null;
        if ($fourth !== null && !$fourth->flags->contains(MetadataFlags::HAS_DEFAULT)) {
            $problems[] = 'it requires more than the three arguments the module handler passes';
        }

        $form = $parameters[0] ?? null;
        if ($form !== null && !$form->flags->contains(MetadataFlags::BY_REFERENCE)) {
            $problems[] = 'the first parameter must be taken by reference';
        }

        if ($form?->declaredType !== null && !Types::isArray($form->declaredType->type)) {
            $problems[] = 'the first parameter must be an array';
        }

        $formState = $parameters[1] ?? null;
        if ($formState?->declaredType !== null && !HookMethods::typed($formState, self::FORM_STATE)) {
            $problems[] = 'the second parameter must be a FormStateInterface';
        }

        $formId = $parameters[2] ?? null;
        if ($formId?->declaredType !== null && !Types::isString($formId->declaredType->type)) {
            $problems[] = 'the third parameter must be a string';
        }

        return $problems;
    }
}
