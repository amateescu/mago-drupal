<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;

use function file_get_contents;
use function in_array;
use function is_file;
use function preg_match;
use function substr;

/**
 * Reads the field type an entity class gives its ID field.
 *
 * The type is set in `baseFieldDefinitions()`: the field created under the
 * literal `id` key or under `$entity_type->getKey('id')`. The class's own
 * method is read first, and one that calls `parent::baseFieldDefinitions()`
 * without setting the ID leads to its parent's.
 *
 * @internal
 */
final class EntityIdField
{
    /**
     * The ID field created with a field type, keyed by the literal `id` or
     * by `$entity_type->getKey('id')`.
     */
    private const ID_FIELD = '/\$fields\[\s*(?:([\'"])id\1|\$entity_type->getKey\(\s*([\'"])id\2\s*\))\s*\]\s*=\s*BaseFieldDefinition::create\(\s*([\'"])(?<type>\w+)\3/';

    private const PARENT_CALL = '/\bparent::baseFieldDefinitions\s*\(/i';

    /**
     * Field types whose values are strings.
     */
    private const STRING_TYPES = ['string', 'uuid'];

    private function __construct() {}

    /**
     * Whether the class's ID field holds strings.
     */
    public static function isString(Codebase $codebase, string $class): bool
    {
        return in_array(self::type($codebase, $class), self::STRING_TYPES, strict: true);
    }

    /**
     * The field type, or null when no method on the way sets it.
     */
    public static function type(Codebase $codebase, string $class): ?string
    {
        $current = $class;
        while ($current !== null) {
            $method = $codebase->getDeclaringMethod($current, 'baseFieldDefinitions');
            $declaring = $method?->identifier->class;
            $file = $method?->location->file;
            if ($method === null || $declaring === null || $file === null || !is_file($file)) {
                return null;
            }

            $span = $method->location->span;
            $body = substr((string) file_get_contents($file), $span->start, $span->length());
            $matches = [];
            if (preg_match(self::ID_FIELD, $body, $matches) === 1) {
                return $matches['type'];
            }

            $current = preg_match(self::PARENT_CALL, $body) === 1
                ? $codebase->getClassLike($declaring)?->directParentClass
                : null;
        }

        return null;
    }
}
