<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\PluginIndex;
use PHPUnit\Framework\TestCase;

final class PluginIndexTest extends TestCase
{
    private const BLOCK = 'Drupal\Core\Block\Attribute\Block';

    public function testASecondClassMakesAClaimAmbiguous(): void
    {
        self::assertSame('Drupal\one\A', PluginIndex::claimed([], 'a', 'Drupal\one\A'));
        // The same class twice is the same declaration read twice.
        self::assertSame('Drupal\one\A', PluginIndex::claimed(['a' => 'Drupal\one\A'], 'a', 'Drupal\one\A'));
        self::assertNull(PluginIndex::claimed(['a' => 'Drupal\one\A'], 'a', 'Drupal\two\A'));
        // An id already ambiguous stays ambiguous, from either side.
        self::assertNull(PluginIndex::claimed(['a' => null], 'a', 'Drupal\one\A'));
        self::assertNull(PluginIndex::claimed(['a' => 'Drupal\one\A'], 'a', null));
    }

    public function testLooksUpByAttributeAndId(): void
    {
        $index = PluginIndex::fromDefinitions([
            self::BLOCK => [
                'page_title_block' => 'Drupal\Core\Block\Plugin\Block\PageTitleBlock',
                'system_menu_block' => 'Drupal\system\Plugin\Block\SystemMenuBlock',
            ],
        ]);

        self::assertSame('Drupal\Core\Block\Plugin\Block\PageTitleBlock', $index->classOf(
            self::BLOCK,
            'page_title_block',
        ));
        self::assertSame('Drupal\system\Plugin\Block\SystemMenuBlock', $index->classOf(
            self::BLOCK,
            'system_menu_block:main',
        ));
        self::assertNull($index->classOf(self::BLOCK, 'missing'));
        self::assertNull($index->classOf('Drupal\Core\Action\Attribute\Action', 'page_title_block'));
        self::assertTrue($index->declares(self::BLOCK, 'page_title_block'));
        self::assertTrue($index->declares(self::BLOCK, 'system_menu_block:main'));
        self::assertFalse($index->declares(self::BLOCK, 'missing'));
        self::assertSame(2, $index->count());
    }

    public function testPrefersAFullIdOverItsBase(): void
    {
        $index = PluginIndex::fromDefinitions([
            'Drupal\Core\Action\Attribute\Action' => [
                'entity' => 'Drupal\Core\Action\Plugin\Action\EntityActionBase',
                'entity:save_action' => 'Drupal\Core\Action\Plugin\Action\SaveAction',
            ],
        ]);

        self::assertSame('Drupal\Core\Action\Plugin\Action\SaveAction', $index->classOf(
            'Drupal\Core\Action\Attribute\Action',
            'entity:save_action',
        ));
        self::assertSame('Drupal\Core\Action\Plugin\Action\EntityActionBase', $index->classOf(
            'Drupal\Core\Action\Attribute\Action',
            'entity:other',
        ));
        // A derivative of a base that holds a colon itself.
        self::assertSame('Drupal\Core\Action\Plugin\Action\SaveAction', $index->classOf(
            'Drupal\Core\Action\Attribute\Action',
            'entity:save_action:node',
        ));
        self::assertTrue($index->declares('Drupal\Core\Action\Attribute\Action', 'entity:save_action:node'));
    }

    public function testTriesEveryBaseLongestFirst(): void
    {
        self::assertSame(
            ['system_menu_block:main', 'system_menu_block'],
            PluginIndex::candidates('system_menu_block:main'),
        );
        self::assertSame(['a:b:c', 'a:b', 'a'], PluginIndex::candidates('a:b:c'));
        self::assertSame(['plain'], PluginIndex::candidates('plain'));
    }
}
