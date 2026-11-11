<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Analyzer\PHPStan\PHPStanIgnoreFilter;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

use function array_diff;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function dirname;
use function file_get_contents;
use function getenv;
use function is_resource;
use function is_string;
use function json_decode;
use function preg_match_all;
use function proc_close;
use function proc_open;
use function sort;
use function stream_get_contents;
use function strpos;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * Holds the Mago issue codes that the docs and the PHPStan comment filter
 * name by hand to the codes that the analyzer has.
 *
 * Nothing else fails when Mago renames a code: the docs would list a code
 * that is never reported, and a comment would stop dropping its issue.
 */
final class AnalyzerCodesTest extends TestCase
{
    private const SECTION = '## Starting from a PHPStan level';

    /**
     * A code in backticks. A setting such as `check-missing-type-hints = true`
     * has more than the name between its backticks.
     */
    private const CODE = '/`([a-z]+(?:-[a-z]+)+)`/';

    public function testThePHPStanLevelTableNamesCodesThatExist(): void
    {
        $page = (string) file_get_contents(dirname(__DIR__, levels: 2) . '/docs/phpstan/index.md');
        $start = strpos($page, self::SECTION);
        self::assertNotFalse($start);
        $end = strpos($page, needle: "\n## ", offset: $start + 1);
        $section = $end === false ? substr($page, $start) : substr($page, $start, $end - $start);
        $matches = [];
        preg_match_all(self::CODE, $section, $matches);
        $named = array_values(array_unique($matches[1]));

        // The table and the list below it name far more codes than this.
        self::assertGreaterThan(40, count($named));
        self::assertSame([], self::unknown($named));
    }

    public function testThePHPStanIdentifiersMapToCodesThatExist(): void
    {
        /** @var array<string, list<string>> $codes */
        $codes = (new ReflectionClassConstant(PHPStanIgnoreFilter::class, 'CODES'))->getValue();

        self::assertSame([], self::unknown(array_values(array_unique(array_merge(...array_values($codes))))));
    }

    /**
     * The names that aren't a code of the analyzer, sorted.
     *
     * @param list<string> $names
     *
     * @return list<string>
     */
    private static function unknown(array $names): array
    {
        $unknown = array_values(array_diff($names, self::analyzerCodes()));
        sort($unknown);

        return $unknown;
    }

    /**
     * Every issue code of the analyzer, as `mago analyze --list-codes` prints
     * them. The `MAGO` variable names another binary, as it does for the
     * recipes in the Justfile.
     *
     * @return list<string>
     */
    private static function analyzerCodes(): array
    {
        /** @var list<string>|null $codes */
        static $codes = null;
        if ($codes !== null) {
            return $codes;
        }

        $root = dirname(__DIR__, levels: 2);
        $binary = getenv('MAGO');
        $pipes = [];
        $process = proc_open(
            [is_string($binary) && $binary !== '' ? $binary : $root . '/vendor/bin/mago', 'analyze', '--list-codes'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
        );
        self::assertIsResource($process);
        $stdout = $pipes[1] ?? null;
        $output = is_resource($stdout) ? (string) stream_get_contents($stdout) : '';
        proc_close($process);

        /** @var mixed $decoded */
        $decoded = json_decode($output, associative: true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $codes = [];
        /** @var mixed $code */
        foreach ($decoded as $code) {
            if (!is_string($code)) {
                continue;
            }

            $codes[] = $code;
        }

        self::assertNotSame([], $codes);

        return $codes;
    }
}
