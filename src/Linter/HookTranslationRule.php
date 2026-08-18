<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\DrupalFile;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Reporting\Issue;

/**
 * Base for rules banning t() inside one particular hook.
 *
 * @internal
 */
abstract class HookTranslationRule implements Rule
{
    /**
     * The hook suffix, without the extension name or leading underscore.
     */
    abstract protected function hook(): string;

    /**
     * The file extension the hook has to live in.
     */
    abstract protected function extension(): string;

    /**
     * The explanation attached to the reported issue.
     */
    abstract protected function help(): string;

    public function lint(LintContext $context): void
    {
        $file = DrupalFile::fromSource($context->file);
        if ($file->extension !== $this->extension()) {
            return;
        }

        $name = Nodes::declaredName($context->file, $context->node);
        if ($name === null || !$file->implementsHook($name, $this->hook())) {
            return;
        }

        $body = Nodes::body($context->file, $context->node);
        if ($body === null) {
            return;
        }

        foreach (Calls::findFunctions($context->file, $body, ['t'])['t'] ?? [] as $call) {
            $context->report(
                Issue::new("Do not use t() in hook_{$this->hook()}().", $call->span)->withHelp($this->help()),
            );
        }
    }
}
