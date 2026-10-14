<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Annotations;
use PHPUnit\Framework\TestCase;

final class AnnotationsTest extends TestCase
{
    public function testReadsAnEntityTypeAnnotation(): void
    {
        $docblock = <<<'DOC'
            /**
             * The node entity class.
             *
             * @ContentEntityType(
             *   id = "node",
             *   label = @Translation("Content"),
             *   label_count = @PluralTranslation(
             *     singular = "@count content item",
             *     plural = "@count content items",
             *   ),
             *   handlers = {
             *     "storage" = "Drupal\node\NodeStorage",
             *     "form" = {
             *       "default" = "Drupal\node\NodeForm",
             *       "delete" = "Drupal\node\Form\NodeDeleteForm"
             *     },
             *     "route_provider" = {
             *       "html" = "Drupal\node\Entity\NodeRouteProvider",
             *     },
             *   },
             *   base_table = "node",
             *   translatable = TRUE,
             *   static_cache = false,
             *   entity_keys = {
             *     "id" = "nid",
             *     "revision" = "vid",
             *   },
             *   config_export = {
             *     "id",
             *     "label",
             *   },
             *   priority = -10,
             *   weight = 1.5,
             * )
             */
            DOC;
        $annotations = Annotations::parse($docblock);

        self::assertCount(1, $annotations);
        $annotation = $annotations[0];
        self::assertSame('ContentEntityType', $annotation->name);
        self::assertSame('node', $annotation->string('id'));
        self::assertSame('Content', $annotation->arguments['label']);
        self::assertSame(
            ['singular' => '@count content item', 'plural' => '@count content items'],
            $annotation->arguments['label_count'],
        );
        self::assertSame(
            [
                'storage' => 'Drupal\node\NodeStorage',
                'form' => ['default' => 'Drupal\node\NodeForm', 'delete' => 'Drupal\node\Form\NodeDeleteForm'],
                'route_provider' => ['html' => 'Drupal\node\Entity\NodeRouteProvider'],
            ],
            $annotation->map('handlers'),
        );
        self::assertTrue($annotation->arguments['translatable']);
        self::assertFalse($annotation->arguments['static_cache']);
        self::assertSame(['id', 'label'], $annotation->map('config_export'));
        self::assertSame(-10, $annotation->arguments['priority']);
        self::assertSame(1.5, $annotation->arguments['weight']);
        self::assertTrue($annotation->has('config_export'));
        self::assertFalse($annotation->has('bundle_label'));
    }

    public function testReadsSeveralAnnotationsAndSkipsProse(): void
    {
        $docblock = <<<'DOC'
            /**
             * Provides a block, see @Block on the next line and user@example.com.
             *
             * @Block(
             *   id = "system_branding_block",
             *   admin_label = @Translation("Site branding"),
             *   forms = {
             *     "settings_tray" = "\Drupal\system\Form\SystemBrandingOffCanvasForm",
             *   },
             * )
             * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0.
             * @see https://www.drupal.org/node/1
             * @Deriver("Drupal\Core\Plugin\Deriver")
             */
            DOC;
        $annotations = Annotations::parse($docblock);

        self::assertSame(
            ['Block', 'Deriver'],
            array_map(static fn($annotation): string => $annotation->name, $annotations),
        );
        self::assertSame('system_branding_block', $annotations[0]->string('id'));
        self::assertSame(
            ['settings_tray' => '\Drupal\system\Form\SystemBrandingOffCanvasForm'],
            $annotations[0]->map('forms'),
        );
        self::assertSame(['Drupal\Core\Plugin\Deriver'], $annotations[1]->arguments);
    }

    public function testSurvivesAnUnterminatedAnnotation(): void
    {
        self::assertSame([], Annotations::parse('/** @Block(id = "x", label = @Translation("y" */'));
        self::assertSame([], Annotations::parse('/** @Block */'));
        self::assertSame('x', Annotations::parse('/** @Block(id = "x", junk = ??? , more = "y") */')[0]->string('id'));
    }

    public function testProseAndCodeSamplesDoNotHideDeclarations(): void
    {
        $docblock = <<<'DOC'
            /**
             * @todo
             * (an unbalanced remark
             *
             * @code
             * @Block(
             *   id = "from_the_sample",
             * )
             * @endcode
             *
             * @Block(
             *   id = "real",
             * )
             */
            DOC;
        $annotations = Annotations::parse($docblock);

        self::assertCount(1, $annotations);
        self::assertSame('real', $annotations[0]->string('id'));
    }

    public function testReadsPositionalIdsColonMapsAndNumberForms(): void
    {
        $element = Annotations::parse('/** @RenderElement("token_tree_table") */')[0];
        self::assertSame('token_tree_table', $element->arguments[0]);

        $numbers = Annotations::parse(
            '/** @Thing(a = {"k": "v"}, b = 1e3, c = .5, d = 0x1F, e = +2, f = \Foo\Bar::BAZ, g = "last") */',
        )[0];
        self::assertSame(['k' => 'v'], $numbers->map('a'));
        self::assertSame(1000.0, $numbers->arguments['b']);
        self::assertSame(0.5, $numbers->arguments['c']);
        self::assertSame(31, $numbers->arguments['d']);
        self::assertSame(2, $numbers->arguments['e']);
        self::assertSame('\Foo\Bar::BAZ', $numbers->arguments['f']);
        self::assertSame('last', $numbers->string('g'));
        self::assertFalse(array_key_exists(0, $numbers->arguments));
    }

    public function testHandlesQuotesAndConstants(): void
    {
        $annotation = Annotations::parse(
            '/** @FieldType(id = \'text\', label = "say ""hi""", cardinality = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, list = {1, 2}) */',
        )[0];

        self::assertSame('text', $annotation->string('id'));
        self::assertSame('say "hi"', $annotation->arguments['label']);
        self::assertSame(
            'FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED',
            $annotation->arguments['cardinality'],
        );
        self::assertSame([1, 2], $annotation->map('list'));
    }
}
