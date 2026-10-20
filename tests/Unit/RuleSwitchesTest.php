<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use amateescu\MagoDrupal\Internal\SkippedInCoreRule;
use amateescu\MagoDrupal\Linter\Rules\DocCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentBlankLineRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentPunctuationRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentRule;
use amateescu\MagoDrupal\Linter\Rules\LongDescriptionPunctuationRule;
use amateescu\MagoDrupal\Linter\Rules\MissingFunctionCommentRule;
use amateescu\MagoDrupal\Linter\Rules\MissingSummaryRule;
use amateescu\MagoDrupal\Linter\Rules\UnusedVariableRule;
use Closure;
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
use function dirname;
use function pack;
use function strlen;
use function strpos;

/**
 * The three checks that core's `phpcs.xml.dist` turns off are rules of their
 * own, so they can leave core's files out. Core runs the missing short
 * description check in some test directories only, so `drupal/missing-summary`
 * runs only there in core's files.
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

    public function testTheRulesCoreExcludesLeaveCoreOut(): void
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
            DrupalExtension::SKIPPED_IN_CORE,
        );

        foreach (DrupalExtension::create()->linterRules as $rule) {
            self::assertTrue($rule->getDefinition()->defaultEnabled, $rule->getDefinition()->code);
            if ($rule->getDefinition()->code !== 'drupal/inline-comment-punctuation') {
                continue;
            }

            self::assertCount(1, self::messages($rule));
            self::assertSame([], self::messages($rule, directory: self::core() . '/modules/node'));
        }
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

        $rule = new MissingSummaryRule();

        self::assertSame($missing, self::messages($rule, $contents, 'core/lib/Drupal/Core'));
        self::assertSame([], self::messages($rule, $contents, self::core() . '/lib/Drupal/Core'));
        self::assertSame($missing, self::messages($rule, $contents, self::core() . '/tests/Drupal/KernelTests/Core'));
        self::assertSame($missing, self::messages($rule, $contents, self::core() . '/modules/node/tests/src/Unit'));
    }

    public function testCoreChecksTheMissingFunctionCommentInCoreDirectoriesOnly(): void
    {
        $contents = "<?php\n\nfunction rule_switches(): int\n{\n    return 0;\n}\n";

        self::assertSame(
            [],
            self::messages(new MissingFunctionCommentRule(), $contents, self::core() . '/modules/node'),
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

    public function testASkippedRuleLintsOutsideCore(): void
    {
        $rule = new SkippedInCoreRule(new InlineCommentPunctuationRule());

        self::assertTrue($rule->getDefinition()->defaultEnabled);
        self::assertSame('drupal/inline-comment-punctuation', $rule->getDefinition()->code);
        self::assertCount(1, self::messages($rule));
        self::assertSame([], self::messages($rule, directory: self::core() . '/modules/node'));
    }

    /**
     * The corpus's stand-in for Drupal core, which holds `core/lib/Drupal.php`.
     */
    private static function core(): string
    {
        return dirname(__DIR__) . '/corpus/core';
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
