<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;

use function array_key_exists;
use function array_slice;
use function count;
use function explode;
use function implode;

/**
 * Maps a plugin attribute class and plugin id to the plugin class.
 *
 * Read from Mago's class metadata: every instantiable class descending from
 * `PluginInspectionInterface` and carrying one of the attributes core's
 * managers discover. That root covers `PluginBase` and the typed-data field
 * types alike. Two classes declaring the same id cancel each other out, so a
 * wrong class is never handed back. The build loop resolves attribute
 * ancestry inline, which is where the branch count comes from.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PluginIndex
{
    public const PLUGIN_ROOT = 'Drupal\Component\Plugin\PluginInspectionInterface';

    /**
     * Fetch classes from the host in slices of this size.
     */
    private const BATCH = 200;

    /**
     * @param array<non-empty-string, array<non-empty-string, non-empty-string|null>> $plugins
     *   Attribute class to plugin id to plugin class; null marks an id two
     *   classes claim.
     */
    private function __construct(
        private readonly array $plugins,
    ) {}

    /**
     * @param list<string> $names Every descendant of the plugin root interface.
     */
    public static function fromDescendants(Codebase $codebase, array $names): self
    {
        $plugins = [];
        $resolved = [];
        $resolver = static function (string $attribute) use ($codebase, &$resolved): ?string {
            /** @var array<string, non-empty-string|null> $resolved */
            if (!array_key_exists($attribute, $resolved)) {
                $resolved[$attribute] = PluginAttribute::discovered(
                    $attribute,
                    $codebase->getClassAncestors($attribute),
                );
            }

            return $resolved[$attribute];
        };
        for ($offset = 0, $total = count($names); $offset < $total; $offset += self::BATCH) {
            foreach ($codebase->getMultipleClasses(array_slice($names, $offset, self::BATCH)) as $class) {
                if ($class === null) {
                    continue;
                }

                self::collect($class, $resolver, $plugins);
            }
        }

        return new self($plugins);
    }

    /**
     * @param array<non-empty-string, array<non-empty-string, non-empty-string>> $plugins
     */
    public static function fromDefinitions(array $plugins): self
    {
        return new self($plugins);
    }

    /**
     * Adds plugins for ids this index does not have yet; null keeps marking
     * an id two annotated classes claim.
     *
     * @param array<non-empty-string, array<non-empty-string, non-empty-string|null>> $plugins
     */
    public function merge(array $plugins): self
    {
        $merged = $this->plugins;
        foreach ($plugins as $attribute => $ids) {
            foreach ($ids as $id => $class) {
                if (array_key_exists($id, $merged[$attribute] ?? [])) {
                    continue;
                }

                $merged[$attribute][$id] = $class;
            }
        }

        return new self($merged);
    }

    /**
     * The class behind a plugin id. A `base:derivative` id that no attribute
     * declares in full resolves through its base. The first id declared
     * decides, even one two classes claim.
     *
     * @return non-empty-string|null
     */
    public function classOf(string $attribute, string $id): ?string
    {
        $ids = $this->plugins[$attribute] ?? [];
        foreach (self::candidates($id) as $candidate) {
            if (array_key_exists($candidate, $ids)) {
                return $ids[$candidate];
            }
        }

        return null;
    }

    /**
     * Whether some scanned class declares the id or one of its bases, even
     * when two do and no class can be handed back for it.
     */
    public function declares(string $attribute, string $id): bool
    {
        $ids = $this->plugins[$attribute] ?? [];
        foreach (self::candidates($id) as $candidate) {
            if (array_key_exists($candidate, $ids)) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->plugins as $ids) {
            $count += count($ids);
        }

        return $count;
    }

    /**
     * The id, then every base it could derive from, longest first. A
     * derivative id is `<base>:<derivative>`, and the base can hold a `:` of
     * its own, as core's `entity:save_action` does.
     *
     * @return non-empty-list<string>
     */
    public static function candidates(string $id): array
    {
        $parts = explode(':', $id);
        $candidates = [];
        for ($count = count($parts); $count > 0; $count--) {
            $candidates[] = implode(':', array_slice($parts, offset: 0, length: $count));
        }

        return $candidates;
    }

    /**
     * The class an id belongs to once `$class` claims it too. A second class
     * claiming the id makes it ambiguous, which null marks; the same class
     * claiming it again does not.
     *
     * @param array<non-empty-string, non-empty-string|null> $ids The claims so far.
     * @param non-empty-string|null $class Null for an id already ambiguous.
     * @return non-empty-string|null
     */
    public static function claimed(array $ids, string $id, ?string $class): ?string
    {
        return array_key_exists($id, $ids) && $ids[$id] !== $class ? null : $class;
    }

    /**
     * @param callable(string): (non-empty-string|null) $resolver
     * @param array<non-empty-string, array<non-empty-string, non-empty-string|null>> $plugins
     */
    private static function collect(ClassLikeMetadata $class, callable $resolver, array &$plugins): void
    {
        $name = Shape::nonEmptyString($class->originalName);
        if ($name === null) {
            return;
        }

        if ($class->kind !== ClassLikeKind::Class_ || $class->flags->contains(MetadataFlags::ABSTRACT)) {
            return;
        }

        foreach (PluginAttribute::read($class->attributes, $resolver) as [$attribute, $id]) {
            $plugins[$attribute][$id] = self::claimed($plugins[$attribute] ?? [], $id, $name);
        }
    }
}
