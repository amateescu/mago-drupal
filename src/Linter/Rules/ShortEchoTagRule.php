<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function strlen;

/**
 * Reports the `<?=` echo tag.
 *
 * Ports Generic.PHP.DisallowShortOpenTag.EchoFound. Mago's
 * `no-short-opening-tag` covers the `<?` tag.
 */
final class ShortEchoTagRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/short-echo-tag',
            name: 'Short echo tag',
            description: 'Reports the <?= echo tag. Drupal writes <?php echo.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::EchoTag],
        );
    }

    public function lint(LintContext $context): void
    {
        // An echo tag with no value, such as an open tag closed at once, is
        // left to `drupal/empty-php-tags`.
        if ($context->file->getFirstDescendant($context->node, NodeKind::Expression) === null) {
            return;
        }

        $start = $context->node->span->start;
        $opener = new Span($start, $start + 3);

        // `<?php echo` needs a space before the value. Spaces and tabs after
        // the tag become that one space, unless a line break follows them.
        // Then the tag alone changes and the line keeps its layout.
        $gap = [];
        preg_match('/\G([ \t]*)(\r|\n)?/', $context->file->contents, $gap, offset: $start + 3);
        $edit = ($gap[2] ?? '') !== ''
            ? TextEdit::replace($opener, '<?php echo')
            : TextEdit::replace(new Span($start, $start + 3 + strlen($gap[1])), '<?php echo ');

        $context->report(Issue::new('Write "<?php echo" instead of the short echo tag.', $opener)->withEdit($edit));
    }
}
