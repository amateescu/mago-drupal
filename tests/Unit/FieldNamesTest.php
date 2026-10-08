<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DrupalRoot;
use amateescu\MagoDrupal\Internal\FieldNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class FieldNamesTest extends TestCase
{
    private const FILES = [
        'core/modules/node/src/Entity/Node.php' => <<<'PHP'
            <?php
            #[ContentEntityType(
              id: 'node',
              entity_keys: [
                'id' => 'nid',
                'bundle' => 'type',
                'owner' => 'uid',
              ],
              revision_metadata_keys: ['revision_user' => 'revision_uid'],
            )]
            class Node extends EditorialContentEntityBase {
              public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
                $fields = parent::baseFieldDefinitions($entity_type);
                $fields['title'] = BaseFieldDefinition::create('string');
                $fields["sticky"] = \Drupal\Core\Field\BaseFieldDefinition::create('boolean');
                return $fields;
              }
              public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions): array {
                return [
                  'version' => BaseFieldDefinition::create('string'),
                  'extra' => FieldStorageDefinition::create('string'),
                ];
              }
            }
            PHP,
        'core/modules/node/src/Plugin/Field/FieldType/NodeItem.php' => <<<'PHP'
            <?php
            $properties['alt'] = DataDefinition::create('string');
            PHP,
        'core/modules/field/field.info.yml' => '',
        'core/modules/comment/src/Entity/Comment.php' => <<<'PHP'
            <?php
            /**
             * @ContentEntityType(
             *   id = "comment",
             *   entity_keys = {
             *     "id" = "cid",
             *     "label" = "subject",
             *   },
             * )
             */
            class Comment extends ContentEntityBase {}
            PHP,
        'modules/foo/foo.module' => <<<'PHP'
            <?php
            function foo_entity_bundle_field_info() {
              $fields['foo_bundle'] = BundleFieldDefinition::create('string');
              FieldStorageConfig::create(['field_name' => 'foo_text', 'entity_type' => 'node'])->save();
              return $fields;
            }
            PHP,
        'modules/foo/foo.install' => <<<'PHP'
            <?php
            function foo_update_10001() {
              \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition('foo_note', 'node', 'foo', $definition);
            }
            PHP,
        'modules/foo/config/install/field.storage.node.body.yml' => '',
        'modules/foo/config/optional/field.field.node.article.comment.yml' => '',
        'modules/foo/config/install/field.settings.yml' => '',
        'core/tests/Drupal/KernelTests/FooTest.php' => <<<'PHP'
            <?php
            $type = new ContentEntityType(['entity_keys' => ['id' => 'tid']]);
            $storage = ['field_name' => 'test_field'];
            $field_name = 'images';
            $this->fieldName = 'test_text';
            $this->createImageField('photos', 'node', 'article');
            PHP,
    ];

    private static string $root = '';

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir() . '/mago-drupal-test-' . uniqid();
        foreach (self::FILES as $path => $contents) {
            $directory = dirname(self::$root . '/' . $path);
            if (!is_dir($directory)) {
                mkdir($directory, recursive: true);
            }

            file_put_contents(self::$root . '/' . $path, $contents);
        }
    }

    public static function tearDownAfterClass(): void
    {
        DiskCacheTest::remove(self::$root);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function names(): iterable
    {
        yield 'entity key of an attribute' => ['nid', true];
        yield 'another entity key' => ['uid', true];
        yield 'revision metadata key' => ['revision_uid', true];
        yield 'entity key of an annotation' => ['subject', true];
        yield 'entity key of a definition array in core tests' => ['tid', true];
        yield 'key a base field definition is set under' => ['title', true];
        yield 'double-quoted key and a qualified class' => ['sticky', true];
        yield 'key in an array literal' => ['version', true];
        yield 'field storage definition' => ['extra', true];
        yield 'field name variable' => ['images', true];
        yield 'field name property' => ['test_text', true];
        yield 'field name a test helper takes' => ['photos', true];
        yield 'field property' => ['alt', false];
        yield 'bundle field in a module file' => ['foo_bundle', true];
        yield 'field storage config created in code' => ['foo_text', true];
        yield 'field storage config created in core tests' => ['test_field', true];
        yield 'field storage an update installs' => ['foo_note', true];
        yield 'field storage config file' => ['body', true];
        yield 'field config file' => ['comment', true];
        yield 'Field UI prefix' => ['field_tags', true];
        yield 'key EntityType fills in' => ['revision_translation_affected', true];
        yield 'ad hoc value' => ['pass_raw', false];
        yield 'entity key name rather than its value' => ['bundle', false];
        yield 'entity type id' => ['node', false];
        yield 'other config file' => ['settings', false];
    }

    #[DataProvider('names')]
    public function testReadsTheNamesFieldsAreDefinedWith(string $name, bool $expected): void
    {
        self::assertSame($expected, DrupalRoot::at(self::$root)->fieldNames()->has($name));
    }

    /**
     * The field module's info file is no field config.
     */
    public function testKeepsTheFieldModule(): void
    {
        self::assertArrayHasKey('field', DrupalRoot::at(self::$root)->modules());
    }

    public function testEveryNameCountsWithoutAnyDefinition(): void
    {
        self::assertTrue(FieldNames::fromFiles([], [])->has('pass_raw'));
    }
}
