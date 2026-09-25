<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use Mago\Sdk\Analyzer\LifecycleContext;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;

/**
 * Reports check findings on the current lifecycle context.
 *
 * @internal
 */
final class Reporter
{
    public readonly PHPVersion $phpVersion;

    public function __construct(
        private readonly LifecycleContext $context,
    ) {
        $this->phpVersion = $context->phpVersion;
    }

    public function error(string $code, Issue $issue): void
    {
        $this->context->report(Level::Error, $code, $issue);
    }

    public function warning(string $code, Issue $issue): void
    {
        $this->context->report(Level::Warning, $code, $issue);
    }

    /**
     * @param Span|SourceLocation $where A node span in the current file, or a
     *   metadata location, which names its own file.
     */
    public static function issue(
        string $message,
        Span|SourceLocation $where,
        ?string $help = null,
        ?string $link = null,
    ): Issue {
        $issue = $where instanceof Span ? Issue::new($message, $where) : Issue::at($message, $where);
        if ($help !== null) {
            $issue = $issue->withHelp($help);
        }

        return $link === null ? $issue : $issue->withLink($link);
    }
}
