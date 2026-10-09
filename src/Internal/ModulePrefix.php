<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;

use function str_starts_with;
use function strtoupper;

/**
 * The module prefix check that `define()` and `const` constants share.
 *
 * @internal
 */
final class ModulePrefix
{
    private function __construct() {}

    /**
     * Returns the prefix a constant needs in this file, or NULL when the file
     * is not a `.module` or `.install` file.
     *
     * The underscore is part of the prefix. For a module named corpus,
     * CORPUSCACHE_TTL does not count as prefixed.
     */
    public static function expected(SourceFile $file): ?string
    {
        $drupalFile = DrupalFile::fromSource($file);
        if (!$drupalFile->isModule() && !$drupalFile->isInstall()) {
            return null;
        }

        return strtoupper($drupalFile->name) . '_';
    }

    /**
     * Builds the issue for $constant when it does not start with $expected.
     */
    public static function issue(string $constant, string $expected, Span $span): ?Issue
    {
        if ($constant === '' || str_starts_with($constant, $expected)) {
            return null;
        }

        return Issue::new(
            "The constant '{$constant}' must start with the module prefix '{$expected}'.",
            $span,
        )->withHelp('Module constants share the global namespace. The prefix keeps them apart.');
    }
}
