<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\TypeComparator;

use function explode;
use function preg_match;
use function strtolower;

/**
 * Drops the invalid return report for a `Response` returned from a form's
 * `buildForm()`.
 *
 * `FormBuilder::retrieveForm()` throws an `EnforcedResponseException` for a
 * `Response` the form returns, and the exception subscriber sends that
 * response instead of the page, so Drupal supports it. `FormInterface`
 * documents an array, so Mago reports the return, even under a native
 * `RedirectResponse|array` that the inherited docblock narrows. A native
 * return type that does not allow the response keeps the report: PHP throws
 * a `TypeError` there before Drupal sees the response.
 *
 * @internal
 */
final class FormResponseReturnFilter implements IssueFilterHook
{
    private const BUILD_FORM = 'buildform';

    private const FORM = 'Drupal\Core\Form\FormInterface';

    private const RESPONSE = 'Symfony\Component\HttpFoundation\Response';

    /**
     * The function and the returned type, as Mago words the report.
     */
    private const MESSAGE = '/^Invalid return type for function `(?<class>[^`]+)::(?<method>\w+)`: .*, but found `(?<found>[^`]+)`\.$/';

    private const CLASS_NAME = '/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';

    public function getCodes(): array
    {
        return ['invalid-return-statement'];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $matches = [];
        if (
            preg_match(self::MESSAGE, $context->issue->message, $matches) !== 1
            || strtolower($matches['method']) !== self::BUILD_FORM
        ) {
            return IssueFilterDecision::Keep;
        }

        $class = $matches['class'];
        $method = $context->codebase->getMethod($class, $matches['method']);
        if (
            $method === null
            || !$context->types->isContainedBy(Type::namedObject($class), Type::namedObject(self::FORM))
        ) {
            return IssueFilterDecision::Keep;
        }

        return self::responses($context->types, $matches['found'], $method->declaredReturnType?->type)
            ? IssueFilterDecision::Remove
            : IssueFilterDecision::Keep;
    }

    /**
     * Whether every returned type is a `Response` the native return type, if
     * there is one, also allows.
     */
    private static function responses(TypeComparator $types, string $found, ?Type $native): bool
    {
        foreach (explode(separator: '|', string: $found) as $name) {
            if (preg_match(self::CLASS_NAME, $name) !== 1) {
                return false;
            }

            $returned = Type::namedObject($name);
            if (
                !$types->isContainedBy($returned, Type::namedObject(self::RESPONSE))
                || $native !== null && !$types->isContainedBy($returned, $native)
            ) {
                return false;
            }
        }

        return true;
    }
}
