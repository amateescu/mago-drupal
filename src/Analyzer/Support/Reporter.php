<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Support;

use amateescu\MagoDrupal\Internal\Text\Utf8Issue;
use Mago\Sdk\Analyzer\LifecycleContext;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;

/**
 * Reports check and hook findings on the current lifecycle context.
 *
 * Every analyzer issue of the extension goes through report(), which makes
 * its text valid UTF-8. Mago stops the whole analysis on an issue whose text
 * isn't.
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
        self::report($this->context, Level::Error, $code, $issue);
    }

    public function warning(string $code, Issue $issue): void
    {
        self::report($this->context, Level::Warning, $code, $issue);
    }

    /**
     * Reports the issue with its text made valid UTF-8.
     */
    public static function report(LifecycleContext $context, Level $level, string $code, Issue $issue): void
    {
        $context->report($level, $code, Utf8Issue::valid($issue));
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
