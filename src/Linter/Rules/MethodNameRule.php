<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Syntax\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function ltrim;
use function preg_match;
use function strtolower;
use function substr;

/**
 * Reports a method name that isn't lowerCamelCase.
 *
 * Ports the NotCamelCaps and ScopeNotCamelCaps codes of
 * Drupal.NamingConventions.ValidFunctionName. The two codes run the same
 * check, with and without a visibility keyword. Mago's `method-name` accepts
 * a run of capitals, as in `getURL()`, which Coder reports.
 */
final class MethodNameRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/method-name',
            name: 'Method name',
            description: "Reports a method name that doesn't use lowerCamelCase.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // The class-like gives the class name for the PHP 4 constructor
            // skip. A Method target doesn't have a parent in its snapshot.
            targets: Nodes::CLASS_LIKE,
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $className = null;
        foreach ($file->getChildren($context->node) as $member) {
            $method = $member->kind === NodeKind::ClassLikeMember ? $file->getChildren($member)[0] ?? null : null;
            if ($method === null || $method->kind !== NodeKind::Method) {
                continue;
            }

            $identifier = Nodes::declaredIdentifier($file, $method);
            if ($identifier === null) {
                continue;
            }

            // Coder strips the leading underscores before the check. Every skip
            // below only lets a name through, so a name that passes the check
            // doesn't need any of them.
            $name = $file->getText($identifier);
            if (self::isCamelCaps(ltrim($name, characters: '_'))) {
                continue;
            }

            $className ??= self::className($file, $context->node);
            if (self::isExempt($name, $className)) {
                continue;
            }

            $context->report(Issue::new(
                "Write the method name {$name}() in lowerCamelCase.",
                $identifier->span,
            )->withHelp(
                'Start with a lower-case letter, use only letters and digits, and write an acronym as Xml, not XML.',
            ));
        }
    }

    /**
     * Coder's `Common::isCamelCaps()` in strict mode: a lower-case ASCII
     * letter, then only ASCII letters and digits, without two capitals in a
     * row. A digit ends a run of capitals.
     */
    private static function isCamelCaps(string $name): bool
    {
        return preg_match('/^[a-z][a-zA-Z0-9]*$/D', $name) === 1 && preg_match('/[A-Z]{2}/', $name) !== 1;
    }

    /**
     * Whether Coder skips the name: a PHP magic method, or a PHP 4
     * constructor or destructor, which is named after the class, or after
     * the class with one leading underscore.
     */
    private static function isExempt(string $name, string $className): bool
    {
        $lower = strtolower($name);
        if (
            preg_match('/^__[^_]/', $name) === 1
            && array_key_exists(substr($lower, offset: 2), MethodNameUnderscoreRule::DOUBLE_UNDERSCORE_NAMES)
        ) {
            return true;
        }

        return $className !== '' && ($lower === $className || $lower === '_' . $className);
    }

    /**
     * The class-like's name in lower case, or an empty string for an
     * anonymous class.
     */
    private static function className(SourceFile $file, Node $class): string
    {
        if ($class->kind === NodeKind::AnonymousClass) {
            return '';
        }

        return strtolower(Nodes::declaredName($file, $class) ?? '');
    }
}
