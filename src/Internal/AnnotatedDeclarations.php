<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function file_get_contents;
use function is_file;
use function is_string;
use function ltrim;
use function preg_match;
use function realpath;
use function sha1;
use function strtolower;

/**
 * Entity types and plugins declared with legacy docblock annotations.
 *
 * Attributes replaced annotations in core, but contrib still ships
 * `@ContentEntityType` and `@Block` docblocks. Mago's metadata carries
 * attributes only, so these are read off the class docblocks of the extension
 * source under the Drupal root.
 * A class carrying the attribute of the same name has its annotation ignored,
 * the way Drupal's discovery does.
 *
 * An analyzed file is read from the text Mago analyzes, which an editor may
 * not have saved (see current()).
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class AnnotatedDeclarations
{
    /**
     * A docblock line starting with an annotation, checked before a file is
     * tokenized.
     */
    public const GATE = '/^\s*\*\s*@[A-Z]/m';

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

    /**
     * Reads the annotated classes of PHP files under the Drupal root off
     * disk, the analyzed ones included: the declarations of each file that
     * has an annotation, by real path, with a hash of the text they were
     * read from.
     *
     * @param list<string> $paths
     * @return array<string, array{string, self}>
     */
    public static function byFile(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            $contents = is_file($path) ? file_get_contents($path) : false;
            if ($contents === false || preg_match(self::GATE, $contents) !== 1) {
                continue;
            }

            $real = realpath($path);
            $files[$real === false ? $path : $real] = [sha1($contents), self::fromContents($contents)];
        }

        return $files;
    }

    /**
     * The declarations in one file's text.
     */
    public static function fromContents(string $contents): self
    {
        return preg_match(self::GATE, $contents) === 1
            ? self::fromClasses(AnnotatedClasses::inContents($contents))
            : new self();
    }

    /**
     * The declarations as the analysis sees them. A file the last codebase
     * scan saw is read from the text Mago analyzes, which an editor may not
     * have saved; every other file from disk. Where the two texts match, the
     * disk entry is used as is.
     *
     * @param array<string, array{string, self}> $disk See byFile().
     * @param array<string, string|null> $scanned Real path to the analyzed
     *   text, or null when that text has no annotation.
     */
    public static function current(array $disk, array $scanned): self
    {
        $sets = [];
        $replaced = [];
        foreach ($scanned as $path => $contents) {
            if ($contents === null) {
                // The analyzed text has none of the annotations the file
                // may have on disk.
                $replaced[$path] = true;
                continue;
            }

            $known = $disk[$path] ?? null;
            if ($known !== null && $known[0] === sha1($contents)) {
                continue;
            }

            $replaced[$path] = true;
            $sets[] = self::fromContents($contents);
        }

        foreach ($disk as $path => [, $set]) {
            if (array_key_exists($path, $replaced)) {
                continue;
            }

            $sets[] = $set;
        }

        return self::mergeAll($sets);
    }

    /**
     * The declarations of classes given as name, docblock, attribute short
     * names and whether the class is abstract.
     *
     * @param list<array{non-empty-string, string, array<string, true>, bool}> $classes
     */
    private static function fromClasses(array $classes): self
    {
        $entityTypes = [];
        /** @var array<non-empty-string, array<non-empty-string, non-empty-string|null>> $plugins */
        $plugins = [];
        $contextKeyed = [];
        $attributes = self::pluginAttributesByShortName();
        foreach ($classes as [$name, $docblock, $attributed, $abstract]) {
            if ($abstract) {
                continue;
            }

            foreach (Annotations::parse($docblock) as $annotation) {
                $short = ClassNames::short($annotation->name);
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

    /**
     * One set holding everything the given sets declare.
     *
     * Reading core produces one set per file, so the sets are folded into
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
            $short = ClassNames::short($attribute);
            if (!array_key_exists($short, $byShortName)) {
                $byShortName[$short] = $attribute;
            }
        }

        return self::$attributesByShortName = $byShortName;
    }
}
