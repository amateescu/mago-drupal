<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Ignore;

/**
 * The PHPStan error identifiers of the linter rules that port a rule of
 * phpstan-drupal, from `tests/data/phpstan-drupal-map.json`, which the
 * package doesn't ship.
 *
 * `just ports-gen` writes this file. Don't edit it by hand.
 *
 * @internal
 */
final class PHPStanDrupalPorts
{
    /**
     * The identifiers of phpstan-drupal's rules, by the linter rule that
     * ports them.
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

    private function __construct() {}
}
