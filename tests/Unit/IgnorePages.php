<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\CoderCodes;
use amateescu\MagoDrupal\Internal\PHPStanPorts;
use RuntimeException;

use function implode;
use function str_replace;

/**
 * Renders the generated parts of the pages about phpcs and PHPStan comments:
 * the names that phpcs comments match besides the Coder map's, and the
 * identifiers of the linter rules that port a PHPStan check.
 */
final class IgnorePages
{
    /**
     * @param array<string, string> $pages The page of each rule and check,
     *   relative to `docs/`.
     */
    public function __construct(
        private readonly array $pages,
    ) {}

    public function phpcsOtherNames(): string
    {
        return $this->lines(CoderCodes::OTHER_NAMES);
    }

    public function phpstanRuleIdentifiers(): string
    {
        return $this->lines([...PHPStanPorts::PORTS, ...PHPStanPorts::CORE]);
    }

    /**
     * Renders a line for each rule, with its names.
     *
     * @param array<string, list<string>> $names
     */
    private function lines(array $names): string
    {
        $lines = [];
        foreach ($names as $code => $list) {
            $page = $this->pages[$code] ?? throw new RuntimeException("No docs entry is named {$code}.");
            $anchor = str_replace(search: '/', replace: '', subject: $code);
            $lines[] = "- [`{$code}`](../{$page}#{$anchor}): `" . implode('`, `', $list) . '`';
        }

        return implode("\n", $lines) . "\n";
    }
}
