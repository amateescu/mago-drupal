<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function str_starts_with;
use function strlen;

/**
 * Reports a byte order mark at the start of a file.
 *
 * Ports Generic.Files.ByteOrderMark.
 */
final class ByteOrderMarkRule implements Rule
{
    /**
     * The marks Coder looks for, in the order it checks them.
     */
    private const MARKS = [
        "\xEF\xBB\xBF" => 'UTF-8',
        "\xFE\xFF" => 'UTF-16 (BE)',
        "\xFF\xFE" => 'UTF-16 (LE)',
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/byte-order-mark',
            name: 'Byte order mark',
            description: 'Reports a UTF-8 or UTF-16 byte order mark at the start of a file.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $contents = $context->file->contents;
        foreach (self::MARKS as $mark => $name) {
            if (!str_starts_with($contents, $mark)) {
                continue;
            }

            $context->report(Issue::new(
                "File contains {$name} byte order mark, which may corrupt your application.",
                new Span(0, strlen($mark)),
            )->withHelp('Remove the mark. PHP sends it to the browser before any code runs.'));

            return;
        }
    }
}
