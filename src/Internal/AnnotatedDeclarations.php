<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function is_string;
use function ltrim;
use function strrchr;
use function strtolower;
use function substr;
use function trim;

/**
 * Entity types and plugins declared with legacy docblock annotations.
 *
 * Attributes replaced annotations in core, but contrib still ships
 * `@ContentEntityType` and `@Block` docblocks. Mago's metadata carries
 * attributes only, so these are read off the class docblocks of scanned files.
 * A class carrying the attribute of the same name has its annotation ignored,
 * the way Drupal's discovery does.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class AnnotatedDeclarations
{
    private const ENTITY_KINDS = [
        'ContentEntityType' => EntityTypeKind::Content,
        'ConfigEntityType' => EntityTypeKind::Config,
        'EntityType' => EntityTypeKind::Base,
    ];

    /**
     * Annotation classes whose short name differs from their attribute's.
     */
    private const RENAMED = [
        'SearchPlugin' => 'Drupal\search\Attribute\Search',
        'FormElement' => 'Drupal\Core\Render\Attribute\RenderElement',
    ];

    /**
     * @var array<string, non-empty-string>|null
     */
    private static ?array $attributesByShortName = null;

    /**
     * @param list<EntityTypeDefinition> $entityTypes
     * @param array<non-empty-string, array<non-empty-string, non-empty-string|null>> $plugins
     *   Attribute class to plugin id to plugin class; null marks an id two
     *   classes claim.
     * @param array<string, true> $contextKeyed Lowercased plugin classes whose
     *   annotation still uses the `context` key.
     */
    public function __construct(
        public readonly array $entityTypes = [],
        public readonly array $plugins = [],
        public readonly array $contextKeyed = [],
    ) {}

    public static function read(SourceFile $file): self
    {
        $entityTypes = [];
        /** @var array<non-empty-string, array<non-empty-string, non-empty-string|null>> $plugins */
        $plugins = [];
        $contextKeyed = [];
        $attributes = self::pluginAttributesByShortName();
        foreach ($file->getNodes(NodeKind::Class_) as $class) {
            $identifier = Nodes::declaredIdentifier($file, $class);
            $name = $identifier === null ? null : Nodes::resolved($file, $identifier);
            $docblock = Docblocks::attachedTo($file, $class);
            if ($name === null || $docblock === null || self::isAbstract($file, $class)) {
                continue;
            }

            $attributed = self::attributeShortNames($file, $class);
            foreach (Annotations::parse($file->getText($docblock)) as $annotation) {
                $short = self::shortName($annotation->name);
                if (array_key_exists($short, $attributed)) {
                    continue;
                }

                $kind = self::ENTITY_KINDS[$short] ?? null;
                if ($kind !== null) {
                    $definition = self::entityType($name, $kind, $annotation);
                    if ($definition !== null) {
                        $entityTypes[] = $definition;
                    }

                    continue;
                }

                $attribute = $attributes[$short] ?? null;
                $id = self::pluginId($annotation);
                if ($attribute === null || $id === null) {
                    continue;
                }

                $plugins[$attribute][$id] = PluginIndex::claimed($plugins[$attribute] ?? [], $id, $name);
                if ($annotation->has('context')) {
                    $contextKeyed[strtolower($name)] = true;
                }
            }
        }

        return new self($entityTypes, $plugins, $contextKeyed);
    }

    public function isEmpty(): bool
    {
        return $this->entityTypes === [] && $this->plugins === [] && $this->contextKeyed === [];
    }

    /**
     * One set holding everything the given sets declare.
     *
     * A scan of core produces one set per file, so the sets are folded into
     * plain arrays here rather than through a per-set merge that would copy
     * the whole plugin map again for every file.
     *
     * @param list<self> $sets
     */
    public static function mergeAll(array $sets): self
    {
        $entityTypes = [];
        /** @var array<non-empty-string, array<non-empty-string, non-empty-string|null>> $plugins */
        $plugins = [];
        $contextKeyed = [];
        foreach ($sets as $set) {
            foreach ($set->entityTypes as $definition) {
                $entityTypes[] = $definition;
            }

            foreach ($set->plugins as $attribute => $ids) {
                foreach ($ids as $id => $class) {
                    $plugins[$attribute][$id] = PluginIndex::claimed($plugins[$attribute] ?? [], $id, $class);
                }
            }

            foreach ($set->contextKeyed as $class => $keyed) {
                $contextKeyed[$class] = $keyed;
            }
        }

        return new self($entityTypes, $plugins, $contextKeyed);
    }

    /**
     * `id = "x"` or, for annotations extending `PluginID`, a lone `"x"`.
     *
     * @return non-empty-string|null
     */
    private static function pluginId(Annotation $annotation): ?string
    {
        return $annotation->string('id') ?? Shape::nonEmptyString($annotation->arguments[0] ?? null);
    }

    /**
     * @param non-empty-string $class
     */
    private static function entityType(
        string $class,
        EntityTypeKind $kind,
        Annotation $annotation,
    ): ?EntityTypeDefinition {
        $id = $annotation->string('id');
        if ($id === null) {
            return null;
        }

        /** @var array<non-empty-string, non-empty-string> $handlers */
        $handlers = [];
        /** @var mixed $value */
        foreach ($annotation->map('handlers') as $type => $value) {
            if (!is_string($type) || $type === '') {
                continue;
            }

            $handler = self::className($value);
            if ($handler !== null) {
                $handlers[$type] = $handler;
                continue;
            }

            /** @var mixed $nested */
            foreach (Shape::array($value) ?? [] as $operation => $nested) {
                $nestedHandler = self::className($nested);
                if (is_string($operation) && $operation !== '' && $nestedHandler !== null) {
                    $handlers[$type . '.' . $operation] = $nestedHandler;
                }
            }
        }

        return new EntityTypeDefinition(
            $id,
            $class,
            $kind,
            EntityTypeAttribute::withDefaults($kind, $handlers),
            $kind !== EntityTypeKind::Config || $annotation->has('config_export'),
        );
    }

    /**
     * @return non-empty-string|null
     */
    private static function className(mixed $value): ?string
    {
        $name = Shape::nonEmptyString($value);

        return $name === null ? null : Shape::nonEmptyString(ltrim($name, characters: '\\'));
    }

    private static function isAbstract(SourceFile $file, Node $class): bool
    {
        foreach ($file->getChildren($class) as $child) {
            if ($child->kind === NodeKind::Modifier && strtolower(trim($file->getText($child))) === 'abstract') {
                return true;
            }
        }

        return false;
    }

    /**
     * Short names of the attributes on the class, as keys.
     *
     * @return array<string, true>
     */
    private static function attributeShortNames(SourceFile $file, Node $class): array
    {
        $names = [];
        foreach ($file->getChildren($class) as $child) {
            if ($child->kind !== NodeKind::AttributeList) {
                continue;
            }

            foreach ($file->getChildren($child) as $attribute) {
                if ($attribute->kind !== NodeKind::Attribute) {
                    continue;
                }

                // The attribute's first identifier is its class name; the
                // arguments come after it.
                foreach ($file->getChildren($attribute) as $part) {
                    $name = $part->kind === NodeKind::Identifier ? Nodes::resolved($file, $part) : null;
                    if ($name !== null) {
                        $names[self::shortName($name)] = true;
                        break;
                    }
                }
            }
        }

        return $names;
    }

    /**
     * Annotation classes and attribute classes share their short name, so
     * `@Block` maps to `Drupal\Core\Block\Attribute\Block`; the renamed ones
     * are listed by hand.
     *
     * @return array<string, non-empty-string>
     */
    private static function pluginAttributesByShortName(): array
    {
        if (self::$attributesByShortName !== null) {
            return self::$attributesByShortName;
        }

        $byShortName = self::RENAMED;
        foreach (PluginManagers::ATTRIBUTES as $attribute) {
            $short = self::shortName($attribute);
            if (!array_key_exists($short, $byShortName)) {
                $byShortName[$short] = $attribute;
            }
        }

        return self::$attributesByShortName = $byShortName;
    }

    private static function shortName(string $class): string
    {
        $tail = strrchr($class, needle: '\\');

        return $tail === false ? $class : substr($tail, offset: 1);
    }
}
