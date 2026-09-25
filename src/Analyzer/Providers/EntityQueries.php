<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\ClassNames;
use amateescu\MagoDrupal\Internal\EntityTypeKind;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

use function array_filter;
use function array_values;
use function in_array;
use function strtolower;

/**
 * Shared shape of the tagged entity query types.
 *
 * A query object type carries four literal parameters, for example
 * `QueryInterface<'node', 'content', 'unchecked', 'ids'>`. They hold the
 * entity type id, its kind, whether `accessCheck()` was called, and whether
 * `count()` was called. Fluent methods return `$this`, so the tags survive a
 * whole chain, and a lifecycle hook reading them needs no index.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class EntityQueries
{
    public const QUERY = 'Drupal\Core\Entity\Query\QueryInterface';

    public const AGGREGATE = 'Drupal\Core\Entity\Query\QueryAggregateInterface';

    public const CHECKED = 'checked';

    public const UNCHECKED = 'unchecked';

    /**
     * The tag after `accessCheck(FALSE)`. Bypassing access is a decision, not
     * an omission.
     */
    public const BYPASSED = 'bypassed';

    /**
     * The entity type tag of a query whose entity type is not known, such as
     * `\Drupal::entityQuery($id)` or `getQuery()` on a bare storage.
     */
    public const UNKNOWN = '*';

    public const CONTENT = 'content';

    /**
     * Config entity queries have no access checking and return string ids.
     */
    public const CONFIG = 'config';

    /**
     * The kind tag when the entity type is not in the index; treated as
     * content, the way phpstan-drupal does.
     */
    public const UNKNOWN_KIND = 'unknown';

    /**
     * Storage types every config entity storage is an instance of. A concrete
     * storage class resolves through the entity type index instead.
     */
    private const CONFIG_STORAGES = [
        'Drupal\Core\Config\Entity\ConfigEntityStorageInterface',
        'Drupal\Core\Config\Entity\ConfigEntityStorage',
    ];

    public const COUNT = 'count';

    public const IDS = 'ids';

    private function __construct() {}

    public static function tagged(string $class, string $entityType, string $kind, string $access, string $result): Type
    {
        return Type::namedObject(
            $class,
            Type::literalString($entityType),
            Type::literalString($kind),
            Type::literalString($access),
            Type::literalString($result),
        );
    }

    public static function kindOf(?EntityTypeKind $kind): string
    {
        return match ($kind) {
            EntityTypeKind::Content => self::CONTENT,
            EntityTypeKind::Config => self::CONFIG,
            default => self::UNKNOWN_KIND,
        };
    }

    /**
     * `accessCheck()` defaults to true. A literal false bypasses access on
     * purpose, which is a decision rather than an omission; a computed flag
     * is treated as checked.
     */
    public static function accessTag(Invocation $invocation): string
    {
        $flag = $invocation->getArgument(0, 'access_check');

        return $flag !== null && $flag->type?->getLiteralBool() === false ? self::BYPASSED : self::CHECKED;
    }

    /**
     * The receiver retagged at one position, for every query object it may
     * be; null when one of them is untagged.
     */
    public static function retagged(Invocation $invocation, int $position, string $value): ?Type
    {
        $all = self::receiverTags($invocation->receiverType);
        if ($all === null) {
            return null;
        }

        $types = [];
        foreach ($all as [$class, $entityType, $kind, $access, $result]) {
            $values = [$entityType, $kind, $access, $result];
            $values[$position] = $value;
            $types[] = self::tagged($class, ...$values);
        }

        return Types::union($types);
    }

    public static function isConfigStorage(?Type $receiver): bool
    {
        foreach ($receiver === null ? [] : $receiver->atomicTypes as $atomic) {
            if ($atomic instanceof NamedObjectType && ClassNames::anyIs([$atomic->name], self::CONFIG_STORAGES)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The tags of every query object the receiver may be, or null when one of
     * them carries none: an untagged query could be anything, so no answer
     * holds for the whole receiver. A call on null is reported on its own, so
     * a null member is skipped.
     *
     * @return non-empty-list<array{string, non-empty-string, non-empty-string, non-empty-string, non-empty-string}>|null
     *   Class, entity type id, kind, access tag, result tag.
     */
    public static function receiverTags(?Type $receiver): ?array
    {
        $all = [];
        foreach (self::members($receiver) as $tags) {
            if ($tags === null) {
                return null;
            }

            $all[] = $tags;
        }

        return $all === [] ? null : $all;
    }

    /**
     * The tags of every tagged query object in a union, untagged ones left
     * out.
     *
     * @return list<array{string, non-empty-string, non-empty-string, non-empty-string, non-empty-string}>
     */
    public static function allTags(?Type $receiver): array
    {
        return array_values(array_filter(self::members($receiver), static fn(?array $tags): bool => $tags !== null));
    }

    /**
     * The tags of each object the receiver may be, null for one without
     * them. A null member is skipped: a call on null is reported on its own.
     *
     * @return list<array{string, non-empty-string, non-empty-string, non-empty-string, non-empty-string}|null>
     */
    private static function members(?Type $receiver): array
    {
        $members = [];
        foreach ($receiver === null ? [] : $receiver->atomicTypes as $atomic) {
            if ($atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Null) {
                continue;
            }

            $members[] = $atomic instanceof NamedObjectType ? self::tagsOf($atomic) : null;
        }

        return $members;
    }

    /**
     * The tags of one query object, or null when it is no query or misses a
     * tag.
     *
     * @return array{string, non-empty-string, non-empty-string, non-empty-string, non-empty-string}|null
     */
    private static function tagsOf(NamedObjectType $atomic): ?array
    {
        if (!in_array(
            strtolower($atomic->name),
            [strtolower(self::QUERY), strtolower(self::AGGREGATE)],
            strict: true,
        )) {
            return null;
        }

        $entityType = ($atomic->parameters[0] ?? null)?->getLiteralString();
        $kind = ($atomic->parameters[1] ?? null)?->getLiteralString();
        $access = ($atomic->parameters[2] ?? null)?->getLiteralString();
        $result = ($atomic->parameters[3] ?? null)?->getLiteralString();
        if (
            $entityType === null
            || $entityType === ''
            || $kind === null
            || $kind === ''
            || $access === null
            || $access === ''
            || $result === null
            || $result === ''
        ) {
            return null;
        }

        return [$atomic->name, $entityType, $kind, $access, $result];
    }
}
