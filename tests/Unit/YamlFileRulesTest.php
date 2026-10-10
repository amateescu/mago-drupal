<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Linter\Rules\InfoAutoAddedKeysRule;
use amateescu\MagoDrupal\Linter\Rules\InfoCoreVersionRequirementRule;
use amateescu\MagoDrupal\Linter\Rules\InfoDependenciesArrayRule;
use amateescu\MagoDrupal\Linter\Rules\InfoDescriptionRule;
use amateescu\MagoDrupal\Linter\Rules\InfoNamespacedDependencyRule;
use amateescu\MagoDrupal\Linter\Rules\RoutingAccessRule;
use Closure;
use Mago\Sdk\CancellationTokenInterface;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function strlen;
use function substr;
use function substr_count;

/**
 * Runs the rules for `.info.yml` and `.routing.yml` files on YAML text. A
 * YAML file has no PHP comments, so the corpus cannot hold `@mago-expect`
 * pragmas for them.
 */
final class YamlFileRulesTest extends TestCase
{
    private const INFO = <<<'YAML'
        name: My module
        type: module
        description: Does things.
        core_version_requirement: ^10 || ^11
        dependencies:
          - drupal:node
        YAML;

    /**
     * @return iterable<string, array{Rule, string, string, list<string>}>
     */
    public static function cases(): iterable
    {
        $packaged =
            self::INFO
            . "\n# Information added by Drupal.org packaging script.\nversion: '1.0.0'\nproject: 'mymodule'\ndatestamp: 1700000000\n";
        yield 'auto-added keys' => [
            new InfoAutoAddedKeysRule(),
            'mymodule/mymodule.info.yml',
            $packaged,
            [
                '9: Remove "project" from the info file. drupal.org packaging adds it.',
                '10: Remove "datestamp" from the info file. drupal.org packaging adds it.',
                '8: Remove "version" from the info file. drupal.org packaging adds it.',
            ],
        ];
        yield 'version in core' => [
            new InfoAutoAddedKeysRule(),
            'core/modules/node/node.info.yml',
            self::INFO . "\nversion: VERSION\n",
            [],
        ];
        yield 'empty key' => [
            new InfoAutoAddedKeysRule(),
            'mymodule/mymodule.info.yml',
            self::INFO . "\nversion: ~\n",
            [],
        ];
        yield 'auto-added keys in another file' => [
            new InfoAutoAddedKeysRule(),
            'mymodule/mymodule.routing.yml',
            "version: 1\n",
            [],
        ];
        yield 'invalid YAML' => [new InfoAutoAddedKeysRule(), 'mymodule/mymodule.info.yml', "version: [\n", []];

        yield 'dependencies as a string' => [
            new InfoDependenciesArrayRule(),
            'mymodule/mymodule.info.yml',
            "name: My module\ndependencies: drupal:node\n",
            [
                '2: The "dependencies" key of the info file must hold a list.',
            ],
        ];
        yield 'dependencies as a list' => [
            new InfoDependenciesArrayRule(),
            'mymodule/mymodule.info.yml',
            self::INFO,
            [],
        ];
        yield 'flow list of dependencies' => [
            new InfoDependenciesArrayRule(),
            'mymodule/mymodule.info.yml',
            "dependencies: [drupal:node]\n",
            [],
        ];

        $dependencies = "name: My module\ntype: module\ndependencies:\n  - node\n  # A comment.\n  - drupal:user\n  - views\npackage: Other\n  - after\n";
        yield 'dependencies without a project' => [
            new InfoNamespacedDependencyRule(),
            'mymodule/mymodule.info.yml',
            $dependencies,
            [
                '4: Prefix the dependency with the name of its project, as in drupal:node.',
                '7: Prefix the dependency with the name of its project, as in drupal:views.',
            ],
        ];
        yield 'dependencies of a theme' => [
            new InfoNamespacedDependencyRule(),
            'mytheme/mytheme.info.yml',
            "type: theme\ndependencies:\n  - node\n",
            [],
        ];
        yield 'flow list without a project' => [
            new InfoNamespacedDependencyRule(),
            'mymodule/mymodule.info.yml',
            "type: module\ndependencies: [node]\n",
            [],
        ];

        yield 'no core version requirement' => [
            new InfoCoreVersionRequirementRule(),
            'mymodule/mymodule.info.yml',
            "name: My module\ntype: module\n",
            [
                '1: The info file has no "core_version_requirement" key.',
            ],
        ];
        yield 'core version requirement' => [
            new InfoCoreVersionRequirementRule(),
            'mymodule/mymodule.info.yml',
            self::INFO,
            [],
        ];
        yield 'test module' => [
            new InfoCoreVersionRequirementRule(),
            'mymodule/mymodule.info.yml',
            "type: module\npackage: Testing\n",
            [],
        ];
        yield 'info file without a type' => [
            new InfoCoreVersionRequirementRule(),
            'mymodule/mymodule.info.yml',
            "name: My module\n",
            [],
        ];
        yield 'config file named like an info file' => [
            new InfoCoreVersionRequirementRule(),
            'config/a.b.info.yml',
            "type: module\n",
            [],
        ];

        yield 'no description' => [
            new InfoDescriptionRule(),
            'mymodule/mymodule.info.yml',
            "name: My module\ntype: module\n",
            [
                '1: The info file has no "description" key.',
            ],
        ];
        yield 'empty description' => [
            new InfoDescriptionRule(),
            'mymodule/mymodule.info.yml',
            "name: My module\ntype: module\ndescription: ''\n",
            [
                '3: The "description" of the info file is empty.',
            ],
        ];
        yield 'description' => [new InfoDescriptionRule(), 'mymodule/mymodule.info.yml', self::INFO, []];

        $routes = <<<'YAML'
            mymodule.open:
              path: '/open'
              requirements:
                _access: 'TRUE'
            mymodule.explained:
              path: '/explained'
              requirements:
                # Anyone may read the help page.
                _access: 'TRUE'
            mymodule.admin:
              path: '/admin/mymodule'
              requirements:
                _permission: 'access administration pages'
            mymodule.boolean:
              requirements:
                _access: TRUE
            YAML;
        yield 'routes' => [
            new RoutingAccessRule(),
            'mymodule/mymodule.routing.yml',
            $routes,
            [
                '4: The route is open to everyone. Say why in a comment on the line above.',
                '13: Use "administer site configuration" for an administration page.',
            ],
        ];
        yield 'routes in another file' => [new RoutingAccessRule(), 'mymodule/mymodule.info.yml', $routes, []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('cases')]
    public function testReportsAsCoderDoes(Rule $rule, string $path, string $contents, array $expected): void
    {
        self::assertSame($expected, self::issues($rule, $path, $contents));
    }

    /**
     * Lints YAML text with one rule and returns each issue as its line and
     * message.
     *
     * @return list<string>
     */
    private static function issues(Rule $rule, string $path, string $contents): array
    {
        $file = new SourceFile(
            PHPVersion::fromParts(8, 1),
            $path,
            $contents,
            [],
            new NodeStore([], '', 0),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore('', 0),
            null,
        );
        $token = new class implements CancellationTokenInterface {
            public function isCancelled(): bool
            {
                return false;
            }

            public function throwIfCancelled(): void {}

            public function subscribe(Closure $callback): int
            {
                return 0;
            }

            public function unsubscribe(int $subscription): void {}
        };
        $context = new LintContext($file, new Node(0, NodeKind::Program, new Span(0, strlen($contents)), null), $token);
        $rule->lint($context);

        return array_map(
            static fn(Issue $issue): string => (
                (
                    substr_count(
                        substr($contents, offset: 0, length: $issue->annotations[0]->span->start),
                        needle: "\n",
                    ) + 1
                )
                . ': '
                . $issue->message
            ),
            $context->issues,
        );
    }
}
