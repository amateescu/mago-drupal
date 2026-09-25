<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ConfigSchema;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use PHPUnit\Framework\TestCase;

use function dirname;
use function implode;

final class ConfigSchemaTest extends TestCase
{
    private static function schema(): ConfigSchema
    {
        return ConfigSchema::fromFiles([
            dirname(__DIR__) . '/fixtures/schema/core.data_types.schema.yml',
            dirname(__DIR__) . '/fixtures/schema/corpus.schema.yml',
            dirname(__DIR__) . '/fixtures/schema/missing.schema.yml',
            dirname(__DIR__) . '/fixtures/schema/malformed.schema.yml',
            dirname(__DIR__) . '/fixtures/schema/override.schema.yml',
        ]);
    }

    public function testResolvesNamesExactlyAndThroughWildcards(): void
    {
        $schema = self::schema();

        self::assertSame('corpus.settings', $schema->definitionName('corpus.settings'));
        self::assertSame('corpus.item.*', $schema->definitionName('corpus.item.foo'));
        self::assertSame('corpus.item.*', $schema->definitionName('corpus.item.foo.bar'));
        // Colons split a segment the way dots do, as in condition plugin ids.
        self::assertSame(
            'condition.plugin.entity_bundle:*',
            $schema->definitionName('condition.plugin.entity_bundle:node'),
        );
        // Trailing wildcards collapse into one when the two-star form is missing.
        self::assertSame('breakpoint.breakpoint.*', $schema->definitionName('breakpoint.breakpoint.olivero.wide'));
        self::assertNull($schema->definitionName('corpus.nothing'));
        self::assertNull($schema->definitionName('nodot'));
        self::assertNull($schema->definitionName(''));
        // The malformed file contributes nothing.
        self::assertNull($schema->definitionName('broken'));
        self::assertSame(13, $schema->count());
    }

    public function testLaterFilesOverrideEarlierDefinitions(): void
    {
        // corpus.loose is redefined by override.schema.yml with an integer name.
        $schema = ConfigSchema::fromFiles([
            dirname(__DIR__) . '/fixtures/schema/override.schema.yml',
        ]);

        self::assertSame(7, $schema->count());
        self::assertTrue($schema->keyExists('corpus.loose', 'name'));
    }

    public function testTypesTheWholeObjectAndSurvivesCycles(): void
    {
        $schema = self::schema();

        self::assertSame(
            'array{_core?: array{default_config_hash: string}, langcode?: string, name: string, count: int, '
            . 'enabled: bool, ratio: float, tags: array<int|string, string>, weights: array<int|string, int>, '
            . 'page: array{front: string}, items: array<int|string, array{limit: int}>, dynamic: mixed, '
            . 'nested: array{limit: int}, note: string|null, flag?: bool}',
            self::render($schema->typeOf('corpus.settings', '')),
        );
        self::assertSame(
            'array{uuid: string, status: bool, dependencies: array<string, mixed>, weight: int}',
            self::render($schema->typeOf('corpus.item.foo', '')),
        );
        self::assertTrue($schema->keyExists('cycle.a', 'anything'));
        self::assertNull($schema->typeOf('cycle.a', 'anything'));
        self::assertFalse($schema->isFullyValidatable('cycle.a'));
    }

    public function testKeepsWhatTheSchemaDoesNotPinDownPermissive(): void
    {
        $schema = self::schema();

        self::assertTrue($schema->isFullyValidatable('untyped.holder'));
        self::assertSame('array<string, mixed>', (string) $schema->typeOf('untyped.holder', 'bare_mapping'));
        // A bare mapping, an ignored value and an unknown type cannot rule
        // any child key out.
        self::assertTrue($schema->keyExists('untyped.holder', 'bare_mapping.anything'));
        self::assertTrue($schema->keyExists('untyped.holder', 'ignored.anything'));
        self::assertTrue($schema->keyExists('untyped.holder', 'undefined.anything'));
        self::assertNull($schema->typeOf('untyped.holder', 'ignored'));
        self::assertNull($schema->typeOf('untyped.holder', 'undefined'));
        self::assertFalse($schema->keyExists('untyped.holder', 'nope'));
        // Inherited mappings merge one level at a time, so a child keeps the
        // parent's keys next to its own.
        self::assertTrue($schema->keyExists('condition.plugin.entity_bundle:node', 'id'));
        self::assertTrue($schema->keyExists('condition.plugin.entity_bundle:node', 'bundles.0'));
        self::assertTrue($schema->isFullyValidatable('condition.plugin.entity_bundle:node'));
    }

    public function testReadsFullyValidatableThroughInheritance(): void
    {
        $schema = self::schema();

        self::assertTrue($schema->isFullyValidatable('corpus.settings'));
        self::assertTrue($schema->isFullyValidatable('corpus.item.foo'));
        self::assertTrue($schema->isFullyValidatable('corpus.inherited'));
        self::assertFalse($schema->isFullyValidatable('corpus.loose'));
        self::assertFalse($schema->isFullyValidatable('corpus.nothing'));
    }

    public function testMapsSchemaTypesToPhpTypes(): void
    {
        $schema = self::schema();

        self::assertSame('string', (string) $schema->typeOf('corpus.settings', 'name'));
        self::assertSame('int', (string) $schema->typeOf('corpus.settings', 'count'));
        self::assertSame('bool', (string) $schema->typeOf('corpus.settings', 'enabled'));
        self::assertSame('float', (string) $schema->typeOf('corpus.settings', 'ratio'));
        self::assertSame('array<int|string, string>', (string) $schema->typeOf('corpus.settings', 'tags'));
        self::assertSame('array<int|string, int>', (string) $schema->typeOf('corpus.settings', 'weights'));
        self::assertSame('int', (string) $schema->typeOf('corpus.settings', 'weights.3'));
        self::assertSame('array{front: string}', self::render($schema->typeOf('corpus.settings', 'page')));
        self::assertSame('string', (string) $schema->typeOf('corpus.settings', 'page.front'));
        self::assertSame('int', (string) $schema->typeOf('corpus.settings', 'items.first.limit'));
        self::assertSame('int', (string) $schema->typeOf('corpus.settings', 'nested.limit'));
        self::assertSame('int', (string) $schema->typeOf('corpus.item.foo', 'weight'));
        // Inherited keys come from the parent config object.
        self::assertSame('string', (string) $schema->typeOf('corpus.settings', 'langcode'));
        self::assertSame('string', (string) $schema->typeOf('corpus.inherited', 'extra'));
        self::assertSame('int', (string) $schema->typeOf('corpus.inherited', 'count'));
    }

    public function testLeavesTheUnknowableUntyped(): void
    {
        $schema = self::schema();

        self::assertNull($schema->typeOf('corpus.settings', 'dynamic'));
        self::assertNull($schema->typeOf('corpus.settings', 'dynamic.anything'));
        self::assertNull($schema->typeOf('corpus.settings', 'missing'));
        self::assertNull($schema->typeOf('corpus.settings', 'page.back'));
        self::assertNull($schema->typeOf('corpus.settings', 'name.deeper'));
        // Not fully validatable, so nothing is promised.
        self::assertNull($schema->typeOf('corpus.loose', 'name'));
        self::assertNull($schema->typeOf('corpus.nothing', 'name'));
    }

    public function testKnowsWhichKeysExist(): void
    {
        $schema = self::schema();

        self::assertTrue($schema->keyExists('corpus.settings', 'name'));
        self::assertTrue($schema->keyExists('corpus.settings', 'page.front'));
        self::assertTrue($schema->keyExists('corpus.settings', 'tags.0'));
        self::assertTrue($schema->keyExists('corpus.settings', 'items.first.limit'));
        self::assertTrue($schema->keyExists('corpus.settings', 'langcode'));
        self::assertTrue($schema->keyExists('corpus.settings', 'dynamic.whatever'));
        self::assertTrue($schema->keyExists('corpus.nothing', 'anything'));
        self::assertFalse($schema->keyExists('corpus.settings', 'missing'));
        self::assertFalse($schema->keyExists('corpus.settings', 'page.back'));
        self::assertFalse($schema->keyExists('corpus.settings', 'name.deeper'));
        self::assertFalse($schema->keyExists('corpus.settings', 'items.first.nope'));
    }

    /**
     * Spells out array shapes, which the SDK describes as a bare `array`.
     */
    private static function render(?Type $type): string
    {
        if ($type === null) {
            return 'null';
        }

        $parts = [];
        foreach ($type->atomicTypes as $atomic) {
            if (!$atomic instanceof KeyedArrayType) {
                $parts[] = (string) $atomic;
                continue;
            }

            if ($atomic->knownItems === null) {
                $parts[] = 'array<' . self::render($atomic->keyType) . ', ' . self::render($atomic->valueType) . '>';
                continue;
            }

            $items = [];
            foreach ($atomic->knownItems as $item) {
                $items[] = (string) $item->key->value . ($item->optional ? '?' : '') . ': ' . self::render($item->type);
            }

            $parts[] = 'array{' . implode(', ', $items) . '}';
        }

        return implode('|', $parts);
    }
}
