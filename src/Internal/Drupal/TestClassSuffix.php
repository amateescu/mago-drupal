<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Drupal;

use amateescu\MagoDrupal\Internal\Declaration\ClassNames;
use amateescu\MagoDrupal\Internal\Text\SourceText;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;

use function preg_match;
use function preg_quote;
use function str_contains;
use function str_ends_with;
use function stripos;

/**
 * Which classes the lint rule `drupal/test-class-suffix` takes for tests,
 * and which misnamed TestCase descendants are left to the analyzer check
 * `drupal/test-class-name`.
 *
 * The lint rule sees one class's syntax and no ancestry. It takes a concrete
 * class for a test when the file is in a PHPUnit test directory and the
 * parent's name, as written, ends in `Test`, `TestBase` or `TestCase`. A
 * parent declared in the same file without such a parent of its own is a
 * fixture, not a test base. The analyzer check reports the TestCase
 * descendants that the lint rule doesn't take for tests, so a class gets one
 * report from the two.
 *
 * @internal
 */
final class TestClassSuffix
{
    /**
     * The PHPUnit test directories of a module.
     */
    private const MODULE_TESTS = '#/tests/src/(?:Unit|Kernel|Functional|FunctionalJavascript|Build)/#';

    /**
     * The PHPUnit test directories of core.
     */
    private const CORE_TESTS = '#/core/tests/Drupal/(?:Tests|KernelTests|FunctionalTests|FunctionalJavascriptTests|BuildTests)/#';

    private const TEST_PARENT = '/(?:Test|TestBase|TestCase)$/';

    /**
     * The first class declaration in a class's text: its modifiers, its name
     * and its parent as written. The text starts at the attributes or the
     * modifiers. Comments may sit between the tokens.
     */
    private const DECLARATION =
        '/(?<![\w$\\\\:])((?:(?:abstract|final|readonly)(?![\w\x80-\xff])'
            . SourceText::GAP
            . ')*+)class(?![\w\x80-\xff])'
            . SourceText::GAP
            . '(\w++)(?:'
            . SourceText::GAP
            . 'extends(?![\w\x80-\xff])'
            . SourceText::GAP
            . '([\\\\\w]++))?/i';

    private function __construct() {}

    /**
     * Whether the file is in a PHPUnit test directory of a module or of core.
     */
    public static function inTestDirectory(string $path): bool
    {
        return preg_match(self::MODULE_TESTS, $path) === 1 || preg_match(self::CORE_TESTS, $path) === 1;
    }

    /**
     * Whether a parent name, as the extends clause writes it, makes the lint
     * rule take its child for a test.
     */
    public static function testParent(string $contents, string $parent): bool
    {
        return preg_match(self::TEST_PARENT, $parent) === 1 && !self::fixtureParent($contents, $parent);
    }

    /**
     * Whether the class text declares a concrete class whose name doesn't end
     * in `Test`, and which the lint rule doesn't take for a test. Most test
     * classes end in `Test`, and don't cost the analyzer check a codebase
     * request then.
     */
    public static function mayBeMisnamed(string $text, string $path, string $contents): bool
    {
        $declaration = [];
        if (
            preg_match(self::DECLARATION, $text, $declaration) !== 1
            || str_ends_with($declaration[2], 'Test')
            || stripos($declaration[1], needle: 'abstract') !== false
        ) {
            return false;
        }

        return !self::inTestDirectory($path) || !self::testParent($contents, $declaration[3] ?? '');
    }

    /**
     * The short name of a concrete class whose name doesn't end in `Test`,
     * or null. The metadata confirms what `mayBeMisnamed()` read from the
     * text.
     */
    public static function misnamed(ClassLikeMetadata $class): ?string
    {
        $shortName = ClassNames::short($class->originalName);

        return $class->flags->contains(MetadataFlags::ABSTRACT) || str_ends_with($shortName, 'Test')
            ? null
            : $shortName;
    }

    /**
     * Whether the file declares the parent, written without a namespace, with
     * no parent whose name ends in `Test`, `TestBase` or `TestCase`. A class
     * name is matched without regard to case, as PHP does. The declaration
     * starts its line, so a docblock that mentions the class doesn't count.
     */
    private static function fixtureParent(string $contents, string $parent): bool
    {
        if (str_contains($parent, '\\')) {
            return false;
        }

        $declaration = [];
        $pattern =
            '/^[ \t]*+(?:(?:abstract|final|readonly)(?![\w\x80-\xff])'
            . SourceText::GAP
            . ')*+class(?![\w\x80-\xff])'
            . SourceText::GAP
            . preg_quote($parent, delimiter: '/')
            . '(?![\w\x80-\xff])(?:'
            . SourceText::GAP
            . 'extends(?![\w\x80-\xff])'
            . SourceText::GAP
            . '([\\\\\w]++))?/mi';
        if (preg_match($pattern, $contents, $declaration) !== 1) {
            return false;
        }

        return preg_match(self::TEST_PARENT, $declaration[1] ?? '') !== 1;
    }
}
