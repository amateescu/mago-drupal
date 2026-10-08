<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;

use function in_array;
use function preg_match;
use function strtolower;

/**
 * Tells entity storage types apart from other types named like them.
 *
 * @internal
 */
final class StorageTypes
{
    private const SUFFIXES = ['Storage', 'StorageInterface'];

    private const STORAGE = 'Drupal\Core\Entity\EntityStorageInterface';

    /**
     * A `@var` tag whose type has a name ending in one of the SUFFIXES.
     */
    private const VAR_TAG = '/@var\s+[^\s*]*Storage(?:Interface)?(?![\w\\\\])/';

    /**
     * The constructor's parameter list, balanced parentheses included.
     */
    private const CONSTRUCTOR = '/function\s++__construct\s*+(\((?:[^()]++|(?1))*+\))/i';

    /**
     * A name ending in one of the SUFFIXES.
     */
    private const NAME = '/Storage(?:Interface)?(?![\w\\\\])/';

    /**
     * A property declaration whose type holds a name ending in one of the
     * SUFFIXES. A method declaration has a parenthesis before its
     * parameters, so it does not match.
     */
    private const PROPERTY = '/\b(?:public|protected|private|var)\b[^;=(){}]*?Storage(?:Interface)?(?![\w\\\\])[^;=(){}]*?\$\w/i';

    /**
     * An import that gives a storage another name, which the class text then
     * uses instead.
     */
    private const ALIASED = '/\buse\s[^;]*Storage(?:Interface)?\s++as\s/i';

    private function __construct() {}

    /**
     * Whether a name looks like a storage: it ends in `Storage` or
     * `StorageInterface`. A cheap gate on the names a class mentions, before
     * any() looks the class up.
     *
     * @param list<non-empty-string> $types
     */
    public static function namedLike(array $types): bool
    {
        return ClassNames::anyEndsWith($types, self::SUFFIXES);
    }

    /**
     * Whether a class's text names a storage where the injection check can
     * report it: in the constructor's parameters, in a property declaration
     * or in a `@var` tag. A method parameter such as `postSave()`'s does not
     * count. A file importing a storage under another name always counts,
     * since the text then shows the alias.
     */
    public static function declaredLike(string $classText, string $fileText): bool
    {
        $matches = [];

        return (
            preg_match(self::CONSTRUCTOR, $classText, $matches) === 1
            && preg_match(self::NAME, $matches[1]) === 1
            || preg_match(self::PROPERTY, $classText) === 1
            || self::documentedLike($classText)
            || preg_match(self::ALIASED, $fileText) === 1
        );
    }

    /**
     * Whether a class's text has a `@var` tag naming a storage. A type that
     * only a docblock names is not among the names the parser resolves, so
     * namedLike() misses a property typed that way.
     */
    public static function documentedLike(string $text): bool
    {
        return preg_match(self::VAR_TAG, $text) === 1;
    }

    /**
     * Whether one of the types is an entity storage: named like one, and
     * `EntityStorageInterface` or a class or interface that extends it. The
     * ancestry decides, since core has storages outside any `Entity`
     * namespace, such as `RoleStorageInterface`, and config and key-value
     * storages share the name ending.
     *
     * @param list<non-empty-string> $types
     */
    public static function any(Codebase $codebase, array $types): bool
    {
        $storage = strtolower(self::STORAGE);
        foreach ($types as $type) {
            if (!self::namedLike([$type])) {
                continue;
            }

            if (strtolower($type) === $storage) {
                return true;
            }

            $class = $codebase->getClassLike($type);
            if ($class !== null && in_array($storage, $class->parentInterfaces, strict: true)) {
                return true;
            }
        }

        return false;
    }
}
