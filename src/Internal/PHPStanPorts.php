<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * The PHPStan error identifiers of the linter rules that port a PHPStan
 * check, for the `@phpstan-ignore` comments that name them.
 *
 * @internal
 */
final class PHPStanPorts
{
    /**
     * The identifiers of phpstan-drupal's rules, by the linter rule that
     * ports them. `PHPStanPortsTest` keeps the list in step with
     * `tests/phpstan-drupal-map.json`, which the package doesn't ship.
     *
     * @var array<string, non-empty-list<string>>
     */
    public const PORTS = [
        'drupal/discouraged-function' => ['discouragedFunctions.discouragedFunctionCalled'],
        'drupal/render-callback' => [
            'renderCallback.expectsArrayOfCallbacks',
            'renderCallback.expectsCallableArrayWithArguments',
            'renderCallback.nonTrustedCallback',
        ],
        'drupal/symfony-yaml-parse' => ['drupal.symfonyYamlParse'],
        'drupal/test-class-suffix' => ['nonAbstractTestClass.NameNoSuffixTest'],
        'drupal/todo-current-issue' => ['drupal.todoCurrentIssue'],
    ];

    /**
     * The rules that port a setting of core's `phpstan.neon.dist`. It
     * disallows the weak hash functions with phpstan-disallowed-calls, which
     * reports a call as `disallowed.function`.
     *
     * @var array<string, non-empty-list<string>>
     */
    public const CORE = [
        'drupal/weak-hash' => ['disallowed.function'],
    ];

    private function __construct() {}

    /**
     * The identifiers of a linter rule, by its code, or null when the rule
     * doesn't port a PHPStan check.
     *
     * @return non-empty-list<string>|null
     */
    public static function of(string $code): ?array
    {
        return self::PORTS[$code] ?? self::CORE[$code] ?? null;
    }
}
