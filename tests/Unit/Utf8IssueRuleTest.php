<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Utf8IssueRule;
use Closure;
use Mago\Sdk\CancellationTokenInterface;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\AnnotationKind;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\TestCase;

/**
 * Mago stops the whole lint run on an issue whose text is not valid UTF-8,
 * so the wrapper must make every text field valid and keep the rest.
 */
final class Utf8IssueRuleTest extends TestCase
{
    private const CONTENTS = "<?php\n\nfunction mymod_caf\xE9(): void {}\n";

    /**
     * Lints the sample with a rule that reports $issue.
     *
     * @return list<Issue>
     */
    private static function lint(Issue $issue): array
    {
        $rule = new class($issue) implements Rule {
            public function __construct(
                private readonly Issue $issue,
            ) {}

            public function getDefinition(): RuleDefinition
            {
                return new RuleDefinition(
                    code: 'drupal/test',
                    name: 'Test',
                    description: 'Reports one issue.',
                    defaultLevel: Level::Error,
                    defaultEnabled: true,
                    targets: [NodeKind::Program],
                );
            }

            public function lint(LintContext $context): void
            {
                $context->report($this->issue);
            }
        };

        $file = new SourceFile(
            PHPVersion::fromParts(8, 1),
            'utf8.module',
            self::CONTENTS,
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
        $context = new LintContext($file, new Node(0, NodeKind::Program, new Span(0, 37), null), $token);
        (new Utf8IssueRule($rule))->lint($context);

        return $context->issues;
    }

    public function testKeepsAValidIssue(): void
    {
        $issue = Issue::new('The function café is fine.', new Span(16, 30), 'here')->withHelp('Rename it.');

        self::assertSame([$issue], self::lint($issue));
    }

    public function testEscapesInvalidBytesInEveryText(): void
    {
        $edit = TextEdit::replace(new Span(16, 30), "mymod_caf\xE9_x");
        $issue = Issue::new("Prefix mymod_caf\xE9() with the module name.", new Span(16, 30), "name \xE9")
            ->withNote("Note \xE9.")
            ->withHelp("Help \xE9 caf\xC3\xA9.")
            ->withLink("https://example.com/\xE9")
            ->withSecondaryAnnotation(new Span(7, 15), "keyword \xE9")
            ->withSecondaryLocation(new SourceLocation('other.module', new Span(0, 5)), "other \xE9")
            ->withEdit($edit);

        $issues = self::lint($issue);

        self::assertCount(1, $issues);
        $rebuilt = $issues[0];
        self::assertSame('Prefix mymod_caf\xE9() with the module name.', $rebuilt->message);
        self::assertSame(['Note \xE9.'], $rebuilt->notes);
        self::assertSame("Help \\xE9 caf\xC3\xA9.", $rebuilt->help);
        self::assertSame('https://example.com/\xE9', $rebuilt->link);
        self::assertSame([$edit], $rebuilt->edits);

        self::assertCount(3, $rebuilt->annotations);
        [$primary, $secondary, $location] = $rebuilt->annotations;
        self::assertSame(AnnotationKind::Primary, $primary->kind);
        self::assertEquals(new Span(16, 30), $primary->span);
        self::assertSame('name \xE9', $primary->message);
        self::assertSame(AnnotationKind::Secondary, $secondary->kind);
        self::assertEquals(new Span(7, 15), $secondary->span);
        self::assertSame('keyword \xE9', $secondary->message);
        self::assertNull($secondary->file);
        self::assertSame('other.module', $location->file);
        self::assertSame('other \xE9', $location->message);
    }

    /**
     * A sequence cut short at the end, a surrogate and an overlong form are
     * all invalid, byte by byte.
     */
    public function testEscapesEachByteOfABrokenSequence(): void
    {
        $issue = Issue::new("a\xE2\x82 b\xED\xA0\x80 c\xC0\xAF d\xE2\x82", new Span(0, 5));

        self::assertSame('a\xE2\x82 b\xED\xA0\x80 c\xC0\xAF d\xE2\x82', self::lint($issue)[0]->message);
    }
}
