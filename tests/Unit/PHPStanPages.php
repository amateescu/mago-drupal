<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Analyzer\PHPStan\PHPStanIgnoreFilter;
use ReflectionClassConstant;
use RuntimeException;

use function array_unique;
use function array_values;
use function explode;
use function implode;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

/**
 * Renders the generated parts of the Coming from PHPStan pages. They are the
 * phpstan-drupal map and the PHPStan identifiers that the `phpstan-ignores`
 * plugin matches.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PHPStanPages
{
    /**
     * @param array<string, string> $pages The page of each rule and check,
     *   relative to `docs/`.
     */
    public function __construct(
        private readonly PHPStanDrupalMap $map,
        private readonly DocsPages $docs,
        private readonly array $pages,
    ) {}

    public function rules(): string
    {
        $sections = [];
        foreach ($this->map->rules as $rule) {
            $lines = [];
            foreach ($rule['identifiers'] as $identifier) {
                $lines[] = self::line(
                    "`{$identifier['identifier']}`",
                    $this->handledBy(PHPStanDrupalMap::where($rule, $identifier)),
                    $identifier['status'],
                    $identifier['note'] ?? '',
                );
            }

            $notes = trim(self::defaultNote($rule['default'] ?? 'on') . ' ' . ($rule['note'] ?? ''));
            $sections[] = "### {$rule['rule']}\n\n" . implode("\n", $lines) . ($notes === '' ? '' : "\n\n{$notes}");
        }

        return implode("\n\n", $sections) . "\n";
    }

    public function extensions(): string
    {
        $lines = [];
        foreach ($this->map->extensions as $extension) {
            $lines[] = self::line(
                "`{$extension['extension']}`",
                $this->handledBy($extension['where']),
                $extension['status'],
                trim(self::defaultNote($extension['default'] ?? 'on') . ' ' . ($extension['note'] ?? '')),
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Renders a line for each identifier, with the Mago issues of each.
     */
    public function identifiers(): string
    {
        /** @var array<string, list<string>> $codes */
        $codes = (new ReflectionClassConstant(PHPStanIgnoreFilter::class, 'CODES'))->getValue();
        $lines = [];
        foreach ($codes as $identifier => $issues) {
            $lines[] = "- `{$identifier}`: `" . implode('`, `', $issues) . '`';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Renders the list item of an identifier or an extension.
     */
    private static function line(string $name, string $by, string $status, string $note): string
    {
        $line = "- {$name}: {$by}" . ($status === 'partial' ? ', partly' : '');

        return $note === '' ? $line : "{$line}. {$note}";
    }

    private static function defaultNote(string $default): string
    {
        return match ($default) {
            'off' => 'Off by default in phpstan-drupal.',
            'manual' => 'phpstan-drupal runs it only when a project adds it to `rules`.',
            default => '',
        };
    }

    /**
     * Names what handles an identifier or an extension.
     *
     * @param list<string> $handlers
     */
    private function handledBy(array $handlers): string
    {
        $names = [];
        foreach ($handlers as $handler) {
            $parts = explode(separator: ':', string: $handler, limit: 2);
            $name = $parts[1] ?? '';
            $names[] = match ($parts[0]) {
                'check', 'ext' => $this->codeLink($name),
                'analyze' => "`mago analyze` (`{$name}`)",
                'doc' => $this->sectionLink($name),
                default => 'nothing',
            };
        }

        return implode(', ', array_values(array_unique($names)));
    }

    private function codeLink(string $code): string
    {
        $page = $this->pages[$code] ?? throw new RuntimeException("No docs entry is named {$code}.");

        return "[`{$code}`](../{$page}#" . str_replace(search: '/', replace: '', subject: $code) . ')';
    }

    /**
     * Links a section of a docs page, named after the page and the heading.
     */
    private function sectionLink(string $target): string
    {
        $parts = explode(separator: '#', string: $target, limit: 2);
        $page = $parts[0];
        $anchor = $parts[1] ?? '';
        $lines = explode(separator: "\n", string: $this->docs->read($page));
        $title = substr($lines[0], offset: 2);
        foreach ($lines as $line) {
            if (!str_starts_with($line, '## ') && !str_starts_with($line, '### ')) {
                continue;
            }

            $heading = trim($line, characters: '# ');
            if (self::slug($heading) === $anchor) {
                return "[{$title}: {$heading}](../{$target})";
            }
        }

        throw new RuntimeException("docs/{$page} has no heading for #{$anchor}.");
    }

    private static function slug(string $heading): string
    {
        $slug = (string) preg_replace('/[^a-z0-9 _-]/', replacement: '', subject: strtolower($heading));

        return (string) preg_replace('/[\s-]+/', replacement: '-', subject: trim($slug));
    }
}
