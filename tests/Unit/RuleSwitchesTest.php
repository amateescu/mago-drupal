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
use amateescu\MagoDrupal\Linter\Rules\MissingFunctionCommentRule;
use amateescu\MagoDrupal\Linter\Rules\MissingSummaryRule;
use amateescu\MagoDrupal\Linter\Rules\UnusedVariableRule;
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
 * own, so `--core` and `--disable` can turn them off by default. Core runs
 * the missing short description check in some test directories only, so
 * `--core` scopes `drupal/missing-summary` to them.
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
                'drupal/author-tag',
                'drupal/case-fall-through',
                'drupal/class-prefix',
                'drupal/const-prefix',
                'drupal/curl-ssl-verify',
                'drupal/escaped-placeholder',
                'drupal/form-alter-comment',
                'drupal/function-name',
                'drupal/function-prefix',
                'drupal/global-class-backslash',
                'drupal/global-constant',
                'drupal/hook-attribute-name',
                'drupal/info-core-version-requirement',
                'drupal/info-dependencies-array',
                'drupal/info-description',
                'drupal/inline-comment-blank-line',
                'drupal/inline-comment-punctuation',
                'drupal/insecure-unserialize',
                'drupal/long-description-punctuation',
                'drupal/method-name',
                'drupal/method-name-underscore',
                'drupal/request-superglobal',
                'drupal/routing-access',
                'drupal/short-list',
                'drupal/strict-config-schema',
                'drupal/unsilenced-deprecation',
                'drupal/untranslated-description',
                'drupal/untranslated-options',
            ],
            self::offCodes(DrupalExtension::fromArguments(['--core'])->linterRules),
        );
        self::assertSame([], self::offCodes(DrupalExtension::fromArguments([])->linterRules));
    }

    public function testCoreChecksTheMissingSummaryInTestDirectoriesOnly(): void
    {
        $contents = <<<'PHP'
            <?php

            /**
             * @return int
             *   Nothing.
             */
            function rule_switches(): int
            {
                // Counts nothing and ends with a letter

                return 0;
            }

            PHP;
        $missing = ['The docblock doesn\'t have a short description.'];

        $core = new MissingSummaryRule(core: true);

        self::assertSame($missing, self::messages(new MissingSummaryRule(), $contents, 'core/lib/Drupal/Core'));
        self::assertSame([], self::messages($core, $contents, 'core/lib/Drupal/Core'));
        self::assertSame($missing, self::messages($core, $contents, 'core/tests/Drupal/KernelTests/Core'));
        self::assertSame($missing, self::messages($core, $contents, 'core/modules/node/tests/src/Unit'));
    }

    public function testCoreChecksTheMissingFunctionCommentInCoreDirectoriesOnly(): void
    {
        $contents = "<?php\n\nfunction rule_switches(): int\n{\n    return 0;\n}\n";

        self::assertSame(
            [],
            self::messages(new MissingFunctionCommentRule(core: true), $contents, 'core/modules/node'),
        );
        self::assertFalse(MissingFunctionCommentRule::checkedOnCore('core/modules/node/node.module'));
        self::assertFalse(MissingFunctionCommentRule::checkedOnCore('core/modules/node/tests/src/Traits/T.php'));
        self::assertFalse(MissingFunctionCommentRule::checkedOnCore('modules/custom/src/Plugin/Block/B.php'));
        self::assertTrue(MissingFunctionCommentRule::checkedOnCore('core/lib/Drupal/Core/Url.php'));
        self::assertTrue(MissingFunctionCommentRule::checkedOnCore('web/core/lib/Drupal/Core/Url.php'));
        self::assertTrue(MissingFunctionCommentRule::checkedOnCore('core/modules/node/src/Plugin/Action/A.php'));
        self::assertTrue(MissingFunctionCommentRule::checkedOnCore('core/modules/node/tests/src/Kernel/K.php'));
        self::assertFalse(MissingFunctionCommentRule::checkedOnCore('core/tests/Drupal/FunctionalTests/F.php'));
        self::assertTrue(MissingFunctionCommentRule::checkedOnCore('core/modules/node/tests/src/Functional/F.php'));
        self::assertTrue(MissingFunctionCommentRule::checkedOnCore(
            'core/modules/mysql/src/Driver/Database/mysql/C.php',
        ));
        self::assertTrue(MissingFunctionCommentRule::checkedOnCore(
            'core/modules/system/tests/modules/HookOrder/hook_order.module',
        ));
    }

    public function testCoreLeavesOutTheFilesCoreSkipsForUnusedVariables(): void
    {
        self::assertFalse(UnusedVariableRule::checkedOnCore('core/modules/node/node.api.php'));
        self::assertFalse(UnusedVariableRule::checkedOnCore('core/lib/Drupal/Component/Transliteration/data/x00.php'));
        self::assertFalse(UnusedVariableRule::checkedOnCore(
            'web/core/lib/Drupal/Component/Transliteration/data/x00.php',
        ));
        self::assertTrue(UnusedVariableRule::checkedOnCore(
            'core/lib/Drupal/Component/Transliteration/PhpTransliteration.php',
        ));
        self::assertTrue(UnusedVariableRule::checkedOnCore('core/modules/node/node.module'));
        self::assertTrue(UnusedVariableRule::checkedOnCore('core/modules/node/src/NodeForm.php'));
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
     * Lints a sample with a docblock and a `//` comment as its trivia, in a
     * file under $directory.
     *
     * @return list<string>
     */
    private static function messages(Rule $rule, string $contents = self::CONTENTS, string $directory = '.'): array
    {
        static $counter = 0;
        ++$counter;
        $docblock = (int) strpos($contents, needle: '/**');
        $comment = (int) strpos($contents, needle: '// Counts');
        $records =
            pack('CNN', 4, $docblock, (int) strpos($contents, needle: '*/', offset: $docblock) + 2)
            . pack('CNN', 1, $comment, (int) strpos($contents, needle: "\n", offset: $comment));
        $file = new SourceFile(
            PHPVersion::fromParts(8, 1),
            "{$directory}/rule-switches-{$counter}.php",
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
