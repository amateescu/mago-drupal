<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use RuntimeException;

use function array_filter;
use function explode;
use function file_get_contents;
use function implode;
use function ksort;
use function sort;
use function str_starts_with;
use function strlen;

/**
 * Renders the PHP classes that hold the ports of the two maps, so the worker
 * doesn't read JSON.
 *
 * `tests/coder-map.json` and `tests/phpstan-drupal-map.json` are written by
 * hand. `just ports-gen` writes the classes from them, and `PortClassesTest`
 * fails when a class is out of date.
 *
 * @mago-expect lint:kan-defect
 */
final class PortClasses
{
    /**
     * The generated classes, relative to the repository root.
     */
    public const GENERATED = [
        'src/Internal/CoderPorts.php',
        'src/Internal/PHPStanDrupalPorts.php',
    ];

    /**
     * The longest line that Mago's formatter keeps on one line.
     */
    private const PRINT_WIDTH = 120;

    public function __construct(
        private readonly string $root,
    ) {}

    public function path(string $file): string
    {
        return $this->root . '/' . $file;
    }

    public function read(string $file): string
    {
        return (string) file_get_contents($this->path($file));
    }

    public function render(string $file): string
    {
        return match ($file) {
            'src/Internal/CoderPorts.php' => $this->coderPorts(),
            'src/Internal/PHPStanDrupalPorts.php' => $this->phpstanDrupalPorts(),
            default => throw new RuntimeException("{$file} isn't a generated class."),
        };
    }

    private function coderPorts(): string
    {
        $map = CoderMap::load($this->root . '/tests/coder-map.json');
        $ports = [];
        foreach ($map->sniffs as $sniff) {
            foreach ($sniff['codes'] as $code) {
                foreach (array_filter(CoderMap::where($sniff, $code), self::isCoderPort(...)) as $handler) {
                    $ports[explode(separator: ':', string: $handler, limit: 2)[1]][] =
                        $sniff['sniff'] . '.' . $code['code'];
                }
            }
        }

        return self::file(
            class: 'CoderPorts',
            summary: [
                'The Coder codes that each rule and analyzer check of this extension ports,',
                'from `tests/coder-map.json`, which the package doesn\'t ship.',
            ],
            constant: [
                'The codes of Coder 9\'s `Drupal` and `DrupalPractice` standards, by the',
                'rule or check that ports them.',
            ],
            ports: $ports,
        );
    }

    private function phpstanDrupalPorts(): string
    {
        $map = PHPStanDrupalMap::load($this->root . '/tests/phpstan-drupal-map.json');
        $ports = [];
        $isRule = static fn(string $handler): bool => str_starts_with($handler, 'ext:');
        foreach ($map->rules as $rule) {
            foreach ($rule['identifiers'] as $identifier) {
                foreach (array_filter(PHPStanDrupalMap::where($rule, $identifier), $isRule) as $handler) {
                    $ports[explode(separator: ':', string: $handler, limit: 2)[1]][] = $identifier['identifier'];
                }
            }
        }

        return self::file(
            class: 'PHPStanDrupalPorts',
            summary: [
                'The PHPStan error identifiers of the linter rules that port a rule of',
                'phpstan-drupal, from `tests/phpstan-drupal-map.json`, which the package',
                'doesn\'t ship.',
            ],
            constant: [
                'The identifiers of phpstan-drupal\'s rules, by the linter rule that',
                'ports them.',
            ],
            ports: $ports,
        );
    }

    /**
     * Whether a handler of the Coder map is a rule or a check of this
     * extension.
     */
    private static function isCoderPort(string $handler): bool
    {
        return str_starts_with($handler, 'ext:') || str_starts_with($handler, 'check:');
    }

    /**
     * Renders a class with a `PORTS` constant, formatted the way Mago's
     * formatter writes it.
     *
     * @param list<string> $summary
     * @param list<string> $constant
     * @param array<string, list<string>> $ports
     */
    private static function file(string $class, array $summary, array $constant, array $ports): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace amateescu\MagoDrupal\Internal;',
            '',
            '/**',
            ...self::comment($summary, indent: ''),
            ' *',
            ' * `just ports-gen` writes this file. Don\'t edit it by hand.',
            ' *',
            ' * @internal',
            ' */',
            "final class {$class}",
            '{',
            '    /**',
            ...self::comment($constant, indent: '    '),
            '     *',
            '     * @var array<string, non-empty-list<string>>',
            '     */',
            '    public const PORTS = [',
            ...self::entries($ports),
            '    ];',
            '',
            '    private function __construct() {}',
            '}',
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * Renders the entries of the `PORTS` constant, sorted, with a list on one
     * line when it fits.
     *
     * @param array<string, list<string>> $ports
     *
     * @return list<string>
     */
    private static function entries(array $ports): array
    {
        ksort($ports);
        $entries = [];
        foreach ($ports as $code => $names) {
            sort($names);
            $quoted = [];
            foreach ($names as $name) {
                $quoted[] = "'{$name}'";
            }

            $line = "        '{$code}' => [" . implode(', ', $quoted) . '],';
            if (strlen($line) <= self::PRINT_WIDTH) {
                $entries[] = $line;
                continue;
            }

            $entries[] = "        '{$code}' => [";
            foreach ($quoted as $name) {
                $entries[] = "            {$name},";
            }

            $entries[] = '        ],';
        }

        return $entries;
    }

    /**
     * Renders the lines of a docblock.
     *
     * @param list<string> $text
     *
     * @return list<string>
     */
    private static function comment(array $text, string $indent): array
    {
        $lines = [];
        foreach ($text as $line) {
            $lines[] = "{$indent} * {$line}";
        }

        return $lines;
    }
}
