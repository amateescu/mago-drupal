<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\EntityTypeDefinition;
use amateescu\MagoDrupal\Internal\EntityTypeIndex;
use amateescu\MagoDrupal\Internal\EntityTypeKind;
use PHPUnit\Framework\TestCase;

final class EntityTypeIndexTest extends TestCase
{
    public function testLooksUpByIdAndReadsHandlers(): void
    {
        $node = new EntityTypeDefinition('node', 'Drupal\node\Entity\Node', EntityTypeKind::Content, handlers: [
            'storage' => 'Drupal\node\NodeStorage',
            'access' => 'Drupal\node\NodeAccessControlHandler',
            'form.default' => 'Drupal\node\NodeForm',
            'form.delete' => 'Drupal\node\Form\NodeDeleteForm',
        ]);
        $index = EntityTypeIndex::fromDefinitions([$node]);

        self::assertSame($node, $index->get('node'));
        self::assertNull($index->get('user'));
        self::assertSame(1, $index->count());
        self::assertSame('Drupal\node\NodeStorage', $node->storage());
        self::assertSame('Drupal\node\NodeAccessControlHandler', $node->handler('access'));
        self::assertSame('Drupal\node\NodeForm', $node->handler('form', 'default'));
        self::assertSame('Drupal\node\Form\NodeDeleteForm', $node->handler('form', 'delete'));
        self::assertNull($node->handler('form', 'edit'));
        self::assertNull($node->handler('view_builder'));
    }

    public function testResolvesAStorageClassOnlyWhenOneTypeUsesIt(): void
    {
        $shared = 'Drupal\Core\Entity\Sql\SqlContentEntityStorage';
        $node = new EntityTypeDefinition('node', 'Drupal\node\Entity\Node', EntityTypeKind::Content, handlers: [
            'storage' => 'Drupal\node\NodeStorage',
        ]);
        $comment = new EntityTypeDefinition(
            'comment',
            'Drupal\comment\Entity\Comment',
            EntityTypeKind::Content,
            handlers: [
                'storage' => $shared,
            ],
        );
        $block = new EntityTypeDefinition(
            'block_content',
            'Drupal\block_content\Entity\BlockContent',
            EntityTypeKind::Content,
            handlers: [
                'storage' => $shared,
            ],
        );
        $bare = new EntityTypeDefinition('bare', 'Drupal\bare\Entity\Bare', EntityTypeKind::Base, handlers: []);
        $index = EntityTypeIndex::fromDefinitions([$node, $comment, $block, $bare]);

        self::assertSame($node, $index->byStorage('Drupal\node\NodeStorage'));
        self::assertSame($comment, $index->byClass('Drupal\comment\Entity\Comment'));
        self::assertNull($index->byClass('Drupal\nowhere\Entity'));
        self::assertNull($index->byStorage($shared));
        self::assertNull($index->byStorage('Drupal\nowhere\Storage'));
        self::assertNull($bare->storage());
    }

    public function testTwoClassesDeclaringOneIdCancelOut(): void
    {
        $first = new EntityTypeDefinition('node', 'Drupal\node\Entity\Node', EntityTypeKind::Content, handlers: [
            'storage' => 'Drupal\node\NodeStorage',
        ]);
        $second = new EntityTypeDefinition(
            'node',
            'Drupal\override\Entity\Node',
            EntityTypeKind::Content,
            handlers: [],
        );
        $index = EntityTypeIndex::fromDefinitions([$first, $second]);

        self::assertNull($index->get('node'));
        self::assertTrue($index->declares('node'));
        self::assertNull($index->byClass('Drupal\node\Entity\Node'));
        self::assertNull($index->byClass('Drupal\override\Entity\Node'));
        self::assertNull($index->byStorage('Drupal\node\NodeStorage'));
        // A later merge cannot bring either back.
        self::assertNull($index->merge([$first])->get('node'));
        self::assertNull($index->merge([$first])->byClass('Drupal\node\Entity\Node'));
    }

    public function testOneClassDeclaredTwiceKeepsItsId(): void
    {
        $first = new EntityTypeDefinition('node', 'Drupal\node\Entity\Node', EntityTypeKind::Content, handlers: []);
        $again = new EntityTypeDefinition('node', 'Drupal\node\Entity\node', EntityTypeKind::Content, handlers: []);

        self::assertSame($again, EntityTypeIndex::fromDefinitions([$first, $again])->get('node'));
    }
}
