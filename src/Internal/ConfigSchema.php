<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayItem;
use Mago\Sdk\Analyzer\Type\ArrayKey;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_shift;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function preg_replace;
use function str_contains;

use const ARRAY_FILTER_USE_BOTH;

/**
 * Config schema read from every `*.schema.yml`, answering what a config key holds.
 *
 * @internal
 *
 * @phpstan-type Definition array<array-key, mixed>
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ConfigSchema
{
    private const STRING_TYPES = [
        'string',
        'label',
        'text',
        'path',
        'uri',
        'email',
        'color_hex',
        'date_format',
        'machine_name',
        'langcode',
        'uuid',
        'required_label',
        'plural_label',
        'bytes',
    ];

    private const INTEGER_TYPES = ['integer', 'weight', 'timestamp'];

    /**
     * Upper bound on `type:` inheritance chains, which guards against cycles.
     */
    private const MAX_DEPTH = 10;

    /**
     * Upper bound on wildcard fallback steps; one step per name segment.
     */
    private const MAX_SEGMENTS = 32;

    /**
     * Resolved definition names per config name, since every provider and
     * hook call resolves the same few names.
     *
     * @var array<string, string|null>
     */
    private array $names = [];

    /**
     * @var array<string, bool>
     */
    private array $validatable = [];

    /**
     * Types per config name and key, since a shape walks the whole mapping
     * below the key.
     *
     * @var array<string, Type|null>
     */
    private array $types = [];

    /**
     * @param array<string, Definition> $definitions
     */
    private function __construct(
        private readonly array $definitions,
    ) {}

    /**
     * @param list<string> $paths
     */
    public static function fromFiles(array $paths): self
    {
        $definitions = [];
        foreach ($paths as $path) {
            try {
                $definitions = [...$definitions, ...self::definitions(Yaml::parseFile($path))];
            } catch (ParseException) {
                // A broken schema file is Drupal's problem to report.
                continue;
            }
        }

        return new self($definitions);
    }

    /**
     * @param array<string, Definition> $definitions
     */
    public static function fromDefinitions(array $definitions): self
    {
        return new self($definitions);
    }

    /**
     * The parsed definitions, the cacheable half of this object.
     *
     * @return array<string, Definition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * The named definitions of one parsed document.
     *
     * @return array<string, Definition>
     */
    private static function definitions(mixed $document): array
    {
        /** @var array<string, Definition> */
        return array_filter(
            Shape::array($document) ?? [],
            static fn(mixed $definition, mixed $name): bool => (
                is_string($name)
                && $name !== ''
                && is_array($definition)
            ),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function count(): int
    {
        return count($this->definitions);
    }

    /**
     * The definition name a config name resolves to, wildcards included.
     */
    public function definitionName(string $configName): ?string
    {
        if (!array_key_exists($configName, $this->names)) {
            $this->names[$configName] = array_key_exists($configName, $this->definitions)
                ? $configName
                : $this->fallbackName($configName);
        }

        return $this->names[$configName];
    }

    /**
     * Whether the schema promises that stored values match it. Only then are
     * types handed out; anywhere else stored values can disagree with the
     * schema.
     */
    public function isFullyValidatable(string $configName): bool
    {
        if (!array_key_exists($configName, $this->validatable)) {
            $name = $this->definitionName($configName);
            $this->validatable[$configName] =
                $name !== null && $this->hasFullyValidatableConstraint($this->definitions[$name], depth: 0);
        }

        return $this->validatable[$configName];
    }

    /**
     * Whether a dotted key is declared, or cannot be ruled out.
     */
    public function keyExists(string $configName, string $key): bool
    {
        $name = $this->definitionName($configName);
        if ($name === null) {
            return true;
        }

        return $this->keyExistsIn($this->definitions[$name], explode('.', $key), depth: 0);
    }

    /**
     * The PHP type stored under a dotted key, or of the whole object for an
     * empty key, or null when the schema does not pin one down.
     */
    public function typeOf(string $configName, string $key): ?Type
    {
        $cacheKey = $configName . "\0" . $key;
        if (array_key_exists($cacheKey, $this->types)) {
            return $this->types[$cacheKey];
        }

        $name = $this->definitionName($configName);
        $type = $name === null || !$this->isFullyValidatable($configName)
            ? null
            : $this->typeIn($this->definitions[$name], $key === '' ? [] : explode('.', $key), depth: 0);

        return $this->types[$cacheKey] = $type;
    }

    /**
     * Drupal's `getFallbackName()`. Replace the last segment with `*`. If that
     * has no definition, collapse trailing wildcards into one and retry. Then
     * move up one segment and start over.
     */
    private function fallbackName(string $name): ?string
    {
        for ($step = 0; $step < self::MAX_SEGMENTS; $step++) {
            $replaced = preg_replace('/([^.:]+)([.:*]*)$/', replacement: '*$2', subject: $name);
            if ($replaced === null || $replaced === $name) {
                return null;
            }

            if (array_key_exists($replaced, $this->definitions)) {
                return $replaced;
            }

            $oneStar = preg_replace('/\.([:.*]*)$/', replacement: '.*', subject: $replaced);
            if ($oneStar !== null && $oneStar !== $replaced && array_key_exists($oneStar, $this->definitions)) {
                return $oneStar;
            }

            $name = $replaced;
        }

        return null;
    }

    /**
     * @param Definition $definition
     */
    private function hasFullyValidatableConstraint(array $definition, int $depth): bool
    {
        if (array_key_exists('FullyValidatable', Shape::arrayAt($definition, 'constraints'))) {
            return true;
        }

        $parent = $this->parentOf($definition);

        return $parent !== null
        && $depth < self::MAX_DEPTH
        && $this->hasFullyValidatableConstraint($parent, $depth + 1);
    }

    /**
     * The definition a `type:` reference names, or null for a scalar or
     * dynamic type.
     *
     * @param Definition $definition
     * @return Definition|null
     */
    private function parentOf(array $definition): ?array
    {
        $type = Shape::string($definition['type'] ?? null);

        return $type === null ? null : $this->definitions[$type] ?? null;
    }

    /**
     * Merges the inherited definitions into this one the way
     * `TypedConfigManager` does: recursively, own values winning.
     *
     * @param Definition $definition
     * @return Definition
     */
    private function resolve(array $definition, int $depth): array
    {
        $parent = $this->parentOf($definition);
        if ($parent === null || $depth >= self::MAX_DEPTH) {
            return $definition;
        }

        return self::mergeDeep($this->resolve($parent, $depth + 1), $definition);
    }

    /**
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $override
     * @return array<array-key, mixed>
     */
    private static function mergeDeep(array $base, array $override): array
    {
        foreach (array_keys($override) as $key) {
            $base[$key] = self::merged($base[$key] ?? null, $override[$key]);
        }

        return $base;
    }

    /**
     * Two arrays merge recursively; anything else is replaced.
     */
    private static function merged(mixed $existing, mixed $value): mixed
    {
        $array = Shape::array($existing);

        return $array !== null && is_array($value) ? self::mergeDeep($array, $value) : $value;
    }

    /**
     * @param Definition $definition
     */
    private static function isDynamic(array $definition): bool
    {
        $type = Shape::string($definition['type'] ?? null);

        return $type !== null && str_contains($type, '[');
    }

    /**
     * The element definition of a sequence, written either as one mapping or
     * as a one-item list.
     *
     * @param Definition $definition
     * @return Definition|null
     */
    private static function sequenceElement(array $definition): ?array
    {
        $sequence = Shape::array($definition['sequence'] ?? null);
        if ($sequence === null) {
            return null;
        }

        return array_key_exists(0, $sequence) ? Shape::array($sequence[0]) : $sequence;
    }

    /**
     * @param Definition $definition
     */
    private static function isSequence(array $definition): bool
    {
        return (
            Shape::string($definition['type'] ?? null) === 'sequence'
            || array_key_exists('sequence', $definition) && !array_key_exists('mapping', $definition)
        );
    }

    /**
     * @param Definition $definition
     * @param list<string> $parts
     */
    private function keyExistsIn(array $definition, array $parts, int $depth): bool
    {
        $definition = $this->resolve($definition, depth: 0);
        if ($parts === [] || $depth >= self::MAX_DEPTH || self::isDynamic($definition)) {
            return true;
        }

        if (self::isSequence($definition)) {
            array_shift($parts);
            $element = self::sequenceElement($definition);

            return $parts === [] || $element === null || $this->keyExistsIn($element, $parts, $depth + 1);
        }

        $mapping = Shape::array($definition['mapping'] ?? null);
        if ($mapping === null) {
            // A scalar has no children. Without `mapping` and without a known
            // scalar type, the schema does not say what is inside.
            $type = Shape::string($definition['type'] ?? null);

            return $type === null || !self::isScalarTypeName($type);
        }

        $child = Shape::array($mapping[array_shift($parts)] ?? null);

        return $child !== null && $this->keyExistsIn($child, $parts, $depth + 1);
    }

    /**
     * @param Definition $definition
     * @param list<string> $parts
     */
    private function typeIn(array $definition, array $parts, int $depth): ?Type
    {
        $definition = $this->resolve($definition, depth: 0);
        if ($depth >= self::MAX_DEPTH) {
            return null;
        }

        if ($parts === []) {
            return $this->typeOfDefinition($definition, $depth);
        }

        if (self::isDynamic($definition)) {
            return null;
        }

        if (self::isSequence($definition)) {
            array_shift($parts);
            $element = self::sequenceElement($definition);
            if ($element === null) {
                return null;
            }

            return $this->typeIn($element, $parts, $depth + 1);
        }

        $child = Shape::array(Shape::arrayAt($definition, 'mapping')[array_shift($parts)] ?? null);

        return $child === null ? null : $this->typeIn($child, $parts, $depth + 1);
    }

    /**
     * @param Definition $definition
     */
    private function typeOfDefinition(array $definition, int $depth): ?Type
    {
        $type = Shape::string($definition['type'] ?? null) ?? '';
        if (in_array($type, self::STRING_TYPES, strict: true)) {
            return Type::string();
        }

        if (in_array($type, self::INTEGER_TYPES, strict: true)) {
            return Type::int();
        }

        if ($type === 'float') {
            return Type::float();
        }

        if ($type === 'boolean') {
            return Type::bool();
        }

        if ($type === 'sequence') {
            return $this->sequenceType($definition, $depth);
        }

        // A definition with keys is a mapping whatever its parent type is
        // called; `config_object` and `config_entity` end up here.
        if ($type === 'mapping' || array_key_exists('mapping', $definition)) {
            return $this->mappingType($definition, $depth);
        }

        return $type === '' ? null : $this->referencedType($type, $depth);
    }

    /**
     * The keys of a mapping as an array shape.
     *
     * Types are only handed out under `FullyValidatable`, where every key is
     * required unless it says `requiredKey: false` and Drupal rejects keys the
     * mapping does not list, so the shape is sealed. A mapping that lists no
     * keys can hold anything.
     *
     * @param Definition $definition
     */
    private function mappingType(array $definition, int $depth): Type
    {
        $mapping = Shape::arrayAt($definition, 'mapping');
        $items = [];
        foreach (array_keys($mapping) as $key) {
            $child = Shape::array($mapping[$key]) ?? [];
            $resolved = $this->resolve($child, depth: 0);
            $type = $this->typeIn($child, [], $depth + 1) ?? Type::mixed();
            if (($resolved['nullable'] ?? false) === true) {
                $type = Type::union($type, Type::null());
            }

            $items[] = new ArrayItem(
                is_int($key) ? new ArrayKey(ArrayKeyKind::Integer, $key) : new ArrayKey(ArrayKeyKind::String, $key),
                ($resolved['requiredKey'] ?? true) === false,
                $type,
            );
        }

        if ($items === []) {
            return Type::array(Type::string(), Type::mixed());
        }

        $required = array_filter($items, static fn(ArrayItem $item): bool => !$item->optional);

        return Type::fromAtomic(new KeyedArrayType($items, keyType: null, valueType: null, nonEmpty: $required !== []));
    }

    /**
     * @param Definition $definition
     */
    private function sequenceType(array $definition, int $depth): Type
    {
        $element = self::sequenceElement($definition);
        $elementType = $element === null || $depth >= self::MAX_DEPTH ? null : $this->typeIn($element, [], $depth + 1);

        return Type::array(Type::union(Type::int(), Type::string()), $elementType ?? Type::mixed());
    }

    private function referencedType(string $type, int $depth): ?Type
    {
        if (str_contains($type, '[') || $depth >= self::MAX_DEPTH) {
            return null;
        }

        $referenced = $this->definitions[$type] ?? null;

        return $referenced === null ? null : $this->typeIn($referenced, [], $depth + 1);
    }

    private static function isScalarTypeName(string $type): bool
    {
        return (
            in_array($type, self::STRING_TYPES, strict: true)
            || in_array($type, self::INTEGER_TYPES, strict: true)
            || in_array($type, ['float', 'boolean'], strict: true)
        );
    }
}
