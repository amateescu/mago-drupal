<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Type;

use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

use function count;

/**
 * Says whether an array type may hold a `[class or object, method]` pair.
 *
 * PHP calls an array only when it holds exactly the keys 0 and 1. The
 * element types aren't checked here.
 *
 * @internal
 */
final class CallablePairs
{
    private function __construct() {}

    /**
     * Whether a keyed array may hold exactly the keys 0 and 1. An array with
     * a key type beyond its known items may hold any keys.
     */
    public static function keyed(KeyedArrayType $array): bool
    {
        if ($array->keyType !== null) {
            return true;
        }

        $indexes = [];
        foreach ($array->knownItems ?? [] as $item) {
            $index = $item->key->kind === ArrayKeyKind::Integer ? $item->key->value : null;
            if ($index === 0 || $index === 1) {
                $indexes[$index] = true;
                continue;
            }

            if (!$item->optional) {
                return false;
            }
        }

        return count($indexes) === 2;
    }

    /**
     * Whether a list may hold two elements. A list whose count is known
     * holds that many. Otherwise a list with elements beyond the known ones
     * may hold any number, and a sealed one, whose element type is `never`,
     * at least its required elements and at most all of them.
     */
    public static function list(ListType $list): bool
    {
        if ($list->knownCount !== null) {
            return $list->knownCount === 2;
        }

        $rest = $list->elementType->atomicTypes;
        $sealed =
            count($rest) === 1
            && $rest[0] instanceof SimpleAtomicType
            && $rest[0]->kind === SimpleAtomicTypeKind::Never;
        if (!$sealed) {
            return true;
        }

        $known = $list->knownElements ?? [];
        $required = 0;
        foreach ($known as $element) {
            $required += $element->optional ? 0 : 1;
        }

        return $required <= 2 && count($known) >= 2;
    }
}
