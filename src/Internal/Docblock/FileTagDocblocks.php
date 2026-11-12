<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Docblock;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Syntax\TriviaKind;

use function in_array;
use function str_contains;

/**
 * Checks every docblock with an `@file` tag in a file, for
 * `drupal/file-comment`.
 *
 * The API module reads only the first file docblock, and an `@defgroup` or
 * `@mainpage` tag needs a docblock of its own. Coder doesn't check either.
 *
 * @internal
 */
final class FileTagDocblocks
{
    private function __construct() {}

    /**
     * Reports a second docblock with an `@file` tag, and an `@defgroup` or
     * `@mainpage` tag in a docblock with an `@file` tag.
     */
    public static function check(LintContext $context): void
    {
        $file = $context->file;
        if (!str_contains($file->contents, '@file')) {
            return;
        }

        $seen = false;
        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            [$fileTag] = Docblocks::fileTag($file, $trivia->span);
            if ($fileTag === null) {
                continue;
            }

            if ($seen) {
                $context->report(Issue::new(
                    'A file has only one @file docblock. Merge this docblock into the first one.',
                    $fileTag,
                ));
            }

            $seen = true;
            foreach (Docblocks::tags($file, $trivia->span) as $tag) {
                if (!in_array($tag->name, ['defgroup', 'mainpage'], strict: true)) {
                    continue;
                }

                $context->report(Issue::new(
                    "Put the @{$tag->name} tag in a docblock of its own, not in the @file docblock.",
                    $tag->nameSpan,
                ));
            }
        }
    }
}
