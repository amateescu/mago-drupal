<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\Arguments;
use amateescu\MagoDrupal\Internal\TestFiles;
use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function str_starts_with;

/**
 * Reports a literal string passed as a form error or a messenger message.
 *
 * DrupalPractice's FormErrorT and MessageT sniffs check the Drupal 7
 * functions form_set_error(), form_error() and drupal_set_message(). This
 * checks the methods that replace them. The receiver type keeps out other classes with
 * the same method names, such as PhpUnitTestDiscovery::addWarning(). The
 * plugin registers one hook for the form state methods and one for the
 * messenger, since the message sits at a different position in each.
 *
 * @internal
 */
final class UntranslatedMessageHook implements MethodCallAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is reported
     * as `drupal/untranslated-message`.
     */
    public const CODE = 'untranslated-message';

    /**
     * The form state methods that set an error, with the message second.
     */
    public const FORM_ERRORS = ['setErrorByName', 'setError'];

    /**
     * The messenger methods that add a message, with the message first.
     */
    public const MESSAGES = ['addMessage', 'addStatus', 'addWarning', 'addError'];

    private const FORM_STATE = 'Drupal\Core\Form\FormStateInterface';

    private const MESSENGER = 'Drupal\Core\Messenger\MessengerInterface';

    /**
     * @param self::FORM_ERRORS|self::MESSAGES $methods
     */
    public function __construct(
        private readonly array $methods,
    ) {}

    public function getTargets(): array
    {
        $interface = $this->methods === self::FORM_ERRORS ? self::FORM_STATE : self::MESSENGER;
        $targets = [];
        foreach ($this->methods as $method) {
            $targets[] = MethodTarget::exact($interface, $method);
        }

        return $targets;
    }

    public function getRequirements(): array
    {
        // The subtree holds the argument, and the text names the arguments
        // of a call that passes them by name.
        return [FileAnalysisRequirement::TargetSubtree, FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if (TestFiles::isTestOrHookDocumentation($context->analysis->file)) {
            return;
        }

        $position = $this->methods === self::FORM_ERRORS ? 1 : 0;
        $argument = Arguments::argument($context, $position, names: 'message');
        if ($argument === null) {
            return;
        }

        // A message that starts with a literal is untranslated even when the
        // rest is a variable, so `'Invalid ' . $name` counts too.
        $source = $context->source;
        $first = Values::leftmost($source, Values::unwrap($source, $argument->value));
        $text = $first->kind === NodeKind::LiteralString ? Values::literalString($source, $first) : null;

        // An empty message marks the element without a message to show, and
        // markup is a render fragment, not a sentence.
        if ($text === null || $text === '' || str_starts_with($text, '<')) {
            return;
        }

        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new('Wrap the message in t() or $this->t().', $argument->value->span)->withHelp(
                'Users see the message, so it must be translatable. Use placeholders for the parts that vary.',
            ),
        );
    }
}
