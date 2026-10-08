<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\PluginAttribute;
use Mago\Sdk\Analyzer\Metadata\AttributeArgumentMetadata;
use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ReferenceSelectorKind;
use Mago\Sdk\Analyzer\Type\ReferenceType;
use Mago\Sdk\Analyzer\Type\ReferenceTypeKind;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;
use PHPUnit\Framework\TestCase;

final class PluginAttributeTest extends TestCase
{
    private const BLOCK = 'Drupal\Core\Block\Attribute\Block';

    private const MEDIA_SOURCE = 'Drupal\media\Attribute\MediaSource';

    private const NEGOTIATION = 'Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl';

    public function testResolvesAttributesThroughTheirAncestors(): void
    {
        self::assertSame(self::BLOCK, PluginAttribute::discovered(self::BLOCK, []));
        self::assertSame(self::BLOCK, PluginAttribute::discovered('drupal\core\block\attribute\block', []));
        self::assertSame(self::MEDIA_SOURCE, PluginAttribute::discovered('Drupal\media\Attribute\OEmbedMediaSource', [
            self::MEDIA_SOURCE,
            'Drupal\Component\Plugin\Attribute\Plugin',
        ]));
        self::assertNull(PluginAttribute::discovered('Drupal\Component\Plugin\Attribute\Plugin', []));
        self::assertNull(PluginAttribute::discovered('Drupal\Core\Entity\Attribute\ContentEntityType', []));
    }

    public function testReadsLiteralIdsOfDiscoveredAttributes(): void
    {
        $resolver = static fn(string $attribute): ?string => (
            $attribute === 'Drupal\corpus\Attribute\SpecialBlock'
                ? self::BLOCK
                : PluginAttribute::discovered($attribute, [])
        );
        $plugins = PluginAttribute::read(
            [
                self::attribute(self::BLOCK, Type::literalString('page_title_block')),
                self::attribute('Drupal\corpus\Attribute\SpecialBlock', Type::literalString('special')),
                self::attribute(self::BLOCK, Type::string()),
                self::attribute('Drupal\Core\Entity\Attribute\ContentEntityType', Type::literalString('node')),
                self::attribute('Drupal\Core\Action\Attribute\Action', Type::literalString('entity:save_action')),
            ],
            $resolver,
            static fn(string $class, string $constant): ?string => null,
        );

        self::assertSame(
            [
                [self::BLOCK, 'page_title_block'],
                [self::BLOCK, 'special'],
                ['Drupal\Core\Action\Attribute\Action', 'entity:save_action'],
            ],
            $plugins,
        );
    }

    public function testReadsIdsHeldInClassConstants(): void
    {
        $constants = [self::NEGOTIATION . '::METHOD_ID' => 'language-url'];
        $plugins = PluginAttribute::read(
            [
                self::attribute(self::BLOCK, self::constant(self::NEGOTIATION, 'METHOD_ID')),
                self::attribute(self::BLOCK, self::constant(self::NEGOTIATION, 'MISSING')),
            ],
            static fn(string $attribute): ?string => PluginAttribute::discovered($attribute, []),
            static fn(string $class, string $constant): ?string => $constants["{$class}::{$constant}"] ?? null,
        );

        self::assertSame([[self::BLOCK, 'language-url']], $plugins);
    }

    /**
     * The type Mago gives an argument naming a class constant.
     */
    private static function constant(string $class, string $constant): Type
    {
        return Type::fromAtomic(
            new ReferenceType(
                ReferenceTypeKind::Member,
                $class,
                null,
                null,
                null,
                $constant,
                ReferenceSelectorKind::Identifier,
            ),
        );
    }

    private static function attribute(string $name, Type $id): AttributeMetadata
    {
        $location = new SourceLocation('Plugin.php', new Span(0, 1));

        return new AttributeMetadata($name, $location, [new AttributeArgumentMetadata(
            'id',
            $location,
            null,
            null,
            $id,
        )]);
    }
}
