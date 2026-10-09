<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use amateescu\MagoDrupal\Internal\DefaultOffRule;
use amateescu\MagoDrupal\Linter\Rules\DocCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentBlankLineRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentPunctuationRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentRule;
use amateescu\MagoDrupal\Linter\Rules\LongDescriptionPunctuationRule;
use Closure;
use InvalidArgumentException;
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
use PHPUnit\Framework\TestCase;

use function array_map;
use function pack;
use function sort;
use function strlen;
use function strpos;

/**
 * The three checks that core's `phpcs.xml.dist` turns off are rules of their
 * own, so `--core` and `--disable` can turn them off by default.
 */
final class RuleSwitchesTest extends TestCase
{
    private const CONTENTS = <<<'PHP'
        <?php

        /**
         * Summary.
         *
         * The long description ends with a letter
         */
        function rule_switches(): int
        {
            // Counts nothing and ends with a letter

            return 0;
        }

        PHP;

    public function testEachCheckReportsUnderItsOwnRule(): void
    {
        self::assertSame(
            ['End an inline comment with a full stop, an exclamation mark, a question mark or a colon.'],
            self::messages(new InlineCommentPunctuationRule()),
        );
        self::assertSame(
            ['Remove the blank line below the comment.'],
            self::messages(new InlineCommentBlankLineRule()),
        );
        self::assertSame(
            ['The long description must end with terminal punctuation.'],
            self::messages(new LongDescriptionPunctuationRule()),
        );
        self::assertSame([], self::messages(new InlineCommentRule()));
        self::assertSame([], self::messages(new DocCommentRule()));
    }

    public function testCoreTurnsOffTheRulesCoreExcludes(): void
    {
        self::assertSame(
            [
                'drupal/class-prefix',
                'drupal/curl-ssl-verify',
                'drupal/form-alter-comment',
                'drupal/function-prefix',
                'drupal/global-constant',
                'drupal/inline-comment-blank-line',
                'drupal/inline-comment-punctuation',
                'drupal/long-description-punctuation',
                'drupal/method-name-underscore',
                'drupal/request-superglobal',
                'drupal/strict-config-schema',
                'drupal/untranslated-options',
            ],
            self::offCodes(DrupalExtension::fromArguments(['--core'])->linterRules),
        );
        self::assertSame([], self::offCodes(DrupalExtension::fromArguments([])->linterRules));
    }

    public function testDisableTurnsOffTheNamedRules(): void
    {
        $rules = DrupalExtension::fromArguments([
            '--disable=drupal/author-tag, drupal/else-if',
            '--disable=drupal/weak-hash,',
        ])->linterRules;

        self::assertSame(['drupal/author-tag', 'drupal/else-if', 'drupal/weak-hash'], self::offCodes($rules));
    }

    public function testDisableRejectsAnUnknownCode(): void
    {
        try {
            DrupalExtension::fromArguments(['--disable=drupal/no-such-rule']);
            self::fail('An unknown code must fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('drupal/no-such-rule', $exception->getMessage());
        }
    }

    public function testARuleThatIsOffStillLints(): void
    {
        $rule = new DefaultOffRule(new InlineCommentPunctuationRule());

        self::assertFalse($rule->getDefinition()->defaultEnabled);
        self::assertSame('drupal/inline-comment-punctuation', $rule->getDefinition()->code);
        self::assertCount(1, self::messages($rule));
    }

    /**
     * @param list<Rule> $rules
     * @return list<string>
     */
    private static function offCodes(array $rules): array
    {
        $codes = [];
        foreach ($rules as $rule) {
            if ($rule->getDefinition()->defaultEnabled) {
                continue;
            }

            $codes[] = $rule->getDefinition()->code;
        }

        sort($codes);

        return $codes;
    }

    /**
     * Lints the sample with a docblock and a `//` comment as its trivia.
     *
     * @return list<string>
     */
    private static function messages(Rule $rule): array
    {
        static $counter = 0;
        ++$counter;

        $contents = self::CONTENTS;
        $docblock = (int) strpos($contents, needle: '/**');
        $comment = (int) strpos($contents, needle: '// Counts');
        $records =
            pack('CNN', 4, $docblock, (int) strpos($contents, needle: '*/', offset: $docblock) + 2)
            . pack('CNN', 1, $comment, (int) strpos($contents, needle: "\n", offset: $comment));
        $file = new SourceFile(
            PHPVersion::fromParts(8, 1),
            "rule-switches-{$counter}.php",
            $contents,
            [],
            new NodeStore([], '', 0),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore($records, 2),
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

        return array_map(static fn(Issue $issue): string => $issue->message, $context->issues);
    }
}
