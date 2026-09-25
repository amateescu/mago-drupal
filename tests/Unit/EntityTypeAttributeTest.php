<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\EntityTypeAttribute;
use amateescu\MagoDrupal\Internal\EntityTypeKind;
use Mago\Sdk\Analyzer\Metadata\AttributeArgumentMetadata;
use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayItem;
use Mago\Sdk\Analyzer\Type\ArrayKey;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;
use PHPUnit\Framework\TestCase;

final class EntityTypeAttributeTest extends TestCase
{
    private const CONTENT = 'Drupal\Core\Entity\Attribute\ContentEntityType';

    private const CONFIG = 'Drupal\Core\Entity\Attribute\ConfigEntityType';

    private const BASE = 'Drupal\Core\Entity\Attribute\EntityType';

    public function testReadsNamedArgumentsAndNestedHandlers(): void
    {
        $definition = EntityTypeAttribute::read('Drupal\node\Entity\Node', [
            self::attribute(self::CONTENT, [
                'id' => Type::literalString('node'),
                'handlers' => self::keyed([
                    'storage' => Type::literalString('Drupal\node\NodeStorage'),
                    'form' => self::keyed([
                        'default' => Type::literalString('Drupal\node\NodeForm'),
                        'delete' => Type::literalString('Drupal\node\Form\NodeDeleteForm'),
                    ]),
                    'computed' => Type::string(),
                ]),
            ]),
        ]);

        self::assertNotNull($definition);
        self::assertSame('node', $definition->id);
        self::assertSame('Drupal\node\Entity\Node', $definition->class);
        self::assertSame(EntityTypeKind::Content, $definition->kind);
        self::assertSame('Drupal\node\NodeStorage', $definition->storage());
        self::assertSame('Drupal\node\NodeForm', $definition->handler('form', 'default'));
        self::assertSame('Drupal\node\Form\NodeDeleteForm', $definition->handler('form', 'delete'));
        self::assertNull($definition->handler('computed'));
        // Defaults fill in only what the attribute left out.
        self::assertSame('Drupal\Core\Entity\EntityAccessControlHandler', $definition->handler('access'));
        self::assertSame('Drupal\Core\Entity\EntityViewBuilder', $definition->handler('view_builder'));
    }

    public function testReadsPositionalHandlersAtTheConstructorPosition(): void
    {
        $arguments = [];
        for ($position = 0; $position < 12; $position++) {
            $arguments[] = self::argument(null, $position === 0 ? Type::literalString('block') : Type::string());
        }

        $arguments[] = self::argument(null, self::keyed([
            'storage' => Type::literalString('Drupal\block\BlockStorage'),
        ]));
        $definition = EntityTypeAttribute::read('Drupal\block\Entity\Block', [
            new AttributeMetadata(self::CONFIG, self::location(), $arguments),
        ]);

        self::assertSame('Drupal\block\BlockStorage', $definition?->storage());
        self::assertSame(EntityTypeKind::Config, $definition?->kind);
    }

    public function testEachKindGetsItsOwnDefaults(): void
    {
        $config = EntityTypeAttribute::read('Drupal\node\Entity\NodeType', [
            self::attribute(self::CONFIG, ['id' => Type::literalString('node_type')]),
        ]);
        $base = EntityTypeAttribute::read('Drupal\test\Entity\Bare', [
            self::attribute(self::BASE, ['id' => Type::literalString('bare')]),
        ]);

        self::assertSame('Drupal\Core\Config\Entity\ConfigEntityStorage', $config?->storage());
        self::assertNull($config?->handler('view_builder'));
        self::assertSame('Drupal\Core\Config\Entity\ConfigEntityTypeInterface', $config?->definitionInterface());
        self::assertSame(EntityTypeKind::Base, $base?->kind);
        self::assertNull($base?->storage());
        self::assertSame('Drupal\Core\Entity\EntityAccessControlHandler', $base?->handler('access'));
        self::assertNull($base?->definitionInterface());
    }

    public function testIgnoresOtherAttributesAndNonLiteralIds(): void
    {
        self::assertNull(EntityTypeAttribute::read('Drupal\test\Plain', [
            self::attribute('Drupal\Core\Block\Attribute\Block', ['id' => Type::literalString('x')]),
        ]));
        self::assertNull(EntityTypeAttribute::read('Drupal\test\Computed', [
            self::attribute(self::CONTENT, ['id' => Type::string()]),
        ]));
        self::assertNull(EntityTypeAttribute::read('', [
            self::attribute(self::CONTENT, ['id' => Type::literalString('x')]),
        ]));
    }

    /**
     * @param array<string, Type> $arguments
     */
    private static function attribute(string $name, array $arguments): AttributeMetadata
    {
        $metadata = [];
        foreach ($arguments as $argument => $type) {
            $metadata[] = self::argument($argument, $type);
        }

        return new AttributeMetadata($name, self::location(), $metadata);
    }

    private static function argument(?string $name, Type $type): AttributeArgumentMetadata
    {
        return new AttributeArgumentMetadata($name, self::location(), null, null, $type);
    }

    /**
     * @param array<string, Type> $items
     */
    private static function keyed(array $items): Type
    {
        $known = [];
        foreach ($items as $key => $type) {
            $known[] = new ArrayItem(new ArrayKey(ArrayKeyKind::String, $key), optional: false, type: $type);
        }

        return Type::fromAtomic(new KeyedArrayType($known, null, null, nonEmpty: true));
    }

    private static function location(): SourceLocation
    {
        return new SourceLocation('Entity.php', new Span(0, 1));
    }
}
