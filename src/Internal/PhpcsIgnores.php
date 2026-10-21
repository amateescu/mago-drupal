<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function array_pop;
use function explode;
use function implode;
use function is_array;
use function str_contains;
use function stripos;
use function substr_count;

/**
 * The lines that a file's phpcs suppression comments cover, and the Coder
 * codes each line ignores.
 *
 * phpcs reads `phpcs:ignore`, `phpcs:disable`, `phpcs:enable`,
 * `phpcs:ignoreFile`, `phpcs:set` and the older `@codingStandardsIgnoreLine`,
 * `@codingStandardsIgnoreStart`, `@codingStandardsIgnoreEnd` and
 * `@codingStandardsIgnoreFile`. `PhpcsIgnoreScan` reads them the way
 * PHP_CodeSniffer 3 does.
 *
 * @internal
 *
 * @phpstan-import-type Ignored from PhpcsIgnoreScan
 */
final class PhpcsIgnores
{
    /**
     * @var FileMemo<?self>|null
     */
    private static ?FileMemo $memo = null;

    /**
     * @param array<int, Ignored> $lines What each covered line ignores.
     */
    private function __construct(
        private readonly bool $file,
        private readonly array $lines,
        private readonly string $eol,
    ) {}

    /**
     * The comments of a file, or null when it doesn't have any. The last
     * file's are kept, since a file's issues are checked in a row.
     */
    public static function of(string $contents): ?self
    {
        self::$memo ??= new FileMemo();

        return self::$memo->get($contents, static fn(): ?self => self::read($contents));
    }

    /**
     * Whether the comments cover the line, numbered from 1, for one of the
     * codes. Each code is a full Coder code, such as
     * `Drupal.Files.LineLength.TooLong`. A comment without codes covers every
     * code, and it's the only kind that covers a line for an empty list.
     *
     * @param list<string> $codes
     */
    public function ignores(int $line, array $codes): bool
    {
        $ignored = $this->file ? [PhpcsIgnoreScan::ALL => true] : $this->lines[$line] ?? [];
        if (array_key_exists(PhpcsIgnoreScan::ALL, $ignored)) {
            return true;
        }

        $except = $ignored[PhpcsIgnoreScan::EXCEPT] ?? [];
        foreach ($codes as $code) {
            if (self::lists($ignored, $code) && !self::lists(is_array($except) ? $except : [], $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The line of the offset, numbered from 1, counting the line ending that
     * phpcs uses for the file.
     */
    public function line(string $contents, int $offset): int
    {
        return substr_count($contents, $this->eol, offset: 0, length: $offset) + 1;
    }

    private static function read(string $contents): ?self
    {
        if (stripos($contents, needle: 'phpcs:') === false && !str_contains($contents, '@codingStandards')) {
            return null;
        }

        [$file, $lines] = PhpcsIgnoreScan::scan($contents);
        if (!$file && $lines === []) {
            return null;
        }

        return new self($file, $lines, PhpcsTokens::lineEnding($contents));
    }

    /**
     * Whether the list holds the code, its sniff, its category or its
     * standard, the names phpcs checks a code against.
     *
     * @param array<string, mixed> $list
     */
    private static function lists(array $list, string $code): bool
    {
        $parts = explode(separator: '.', string: $code);
        while ($parts !== []) {
            if (array_key_exists(implode('.', $parts), $list)) {
                return true;
            }

            array_pop($parts);
        }

        return false;
    }
}
