<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;

use function strtolower;

/**
 * Which plugin attribute each core plugin manager discovers.
 *
 * A manager names its attribute class in its constructor, which metadata does
 * not expose, so the pairs for core's attribute-based managers are listed
 * here, read off Drupal 11.4. A contrib manager that extends one of these, or
 * a receiver typed by the manager's interface, is recognised through the class
 * ancestry. Keys are compared case-insensitively.
 *
 * @internal
 */
final class PluginManagers
{
    public const ATTRIBUTES = [
        'Drupal\Core\Block\BlockManagerInterface' => 'Drupal\Core\Block\Attribute\Block',
        'Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface' => 'Drupal\Core\Entity\Attribute\EntityReferenceSelection',
        'Drupal\Core\Field\FieldTypePluginManagerInterface' => 'Drupal\Core\Field\Attribute\FieldType',
        'Drupal\Core\Layout\LayoutPluginManagerInterface' => 'Drupal\Core\Layout\Attribute\Layout',
        'Drupal\Core\Mail\MailManagerInterface' => 'Drupal\Core\Mail\Attribute\Mail',
        'Drupal\Core\Queue\QueueWorkerManagerInterface' => 'Drupal\Core\Queue\Attribute\QueueWorker',
        'Drupal\Core\Action\ActionManager' => 'Drupal\Core\Action\Attribute\Action',
        'Drupal\Core\Archiver\ArchiverManager' => 'Drupal\Core\Archiver\Attribute\Archiver',
        'Drupal\Core\Block\BlockManager' => 'Drupal\Core\Block\Attribute\Block',
        'Drupal\Core\Condition\ConditionManager' => 'Drupal\Core\Condition\Attribute\Condition',
        'Drupal\Core\Config\Action\ConfigActionManager' => 'Drupal\Core\Config\Action\Attribute\ConfigAction',
        'Drupal\Core\Display\VariantManager' => 'Drupal\Core\Display\Attribute\DisplayVariant',
        'Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager' => 'Drupal\Core\Entity\Attribute\EntityReferenceSelection',
        'Drupal\Core\Field\FieldTypePluginManager' => 'Drupal\Core\Field\Attribute\FieldType',
        'Drupal\Core\Field\FormatterPluginManager' => 'Drupal\Core\Field\Attribute\FieldFormatter',
        'Drupal\Core\Field\WidgetPluginManager' => 'Drupal\Core\Field\Attribute\FieldWidget',
        'Drupal\Core\ImageToolkit\ImageToolkitManager' => 'Drupal\Core\ImageToolkit\Attribute\ImageToolkit',
        'Drupal\Core\ImageToolkit\ImageToolkitOperationManager' => 'Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation',
        'Drupal\Core\Layout\LayoutPluginManager' => 'Drupal\Core\Layout\Attribute\Layout',
        'Drupal\Core\Mail\MailManager' => 'Drupal\Core\Mail\Attribute\Mail',
        'Drupal\Core\Queue\QueueWorkerManager' => 'Drupal\Core\Queue\Attribute\QueueWorker',
        'Drupal\Core\Render\ElementInfoManager' => 'Drupal\Core\Render\Attribute\RenderElement',
        'Drupal\Core\Theme\Icon\IconExtractorPluginManager' => 'Drupal\Core\Theme\Icon\Attribute\IconExtractor',
        'Drupal\Core\TypedData\TypedDataManager' => 'Drupal\Core\TypedData\Attribute\DataType',
        'Drupal\ckeditor5\Plugin\CKEditor5PluginManager' => 'Drupal\ckeditor5\Attribute\CKEditor5Plugin',
        'Drupal\editor\Plugin\EditorManager' => 'Drupal\editor\Attribute\Editor',
        'Drupal\filter\FilterPluginManager' => 'Drupal\filter\Attribute\Filter',
        'Drupal\help\HelpSectionManager' => 'Drupal\help\Attribute\HelpSection',
        'Drupal\image\ImageEffectManager' => 'Drupal\image\Attribute\ImageEffect',
        'Drupal\language\LanguageNegotiationMethodManager' => 'Drupal\language\Attribute\LanguageNegotiation',
        'Drupal\layout_builder\SectionStorage\SectionStorageManager' => 'Drupal\layout_builder\Attribute\SectionStorage',
        'Drupal\media\MediaSourceManager' => 'Drupal\media\Attribute\MediaSource',
        'Drupal\navigation\TopBarItemManager' => 'Drupal\navigation\Attribute\TopBarItem',
        'Drupal\rest\Plugin\Type\ResourcePluginManager' => 'Drupal\rest\Attribute\RestResource',
        'Drupal\search\SearchPluginManager' => 'Drupal\search\Attribute\Search',
        'Drupal\workflows\WorkflowTypeManager' => 'Drupal\workflows\Attribute\WorkflowType',
    ];

    /**
     * The plugin a `FallbackPluginManagerInterface` manager returns for an
     * unknown id instead of throwing, keyed by attribute class.
     */
    public const FALLBACKS = [
        'Drupal\Core\Block\Attribute\Block' => 'broken',
        'Drupal\Core\Entity\Attribute\EntityReferenceSelection' => 'broken',
        'Drupal\filter\Attribute\Filter' => 'filter_null',
    ];

    /**
     * One core plugin id per attribute that is always present when core's
     * plugins were scanned. An unknown id is only reported once its sentinel
     * is in the index; otherwise core is simply outside the analyzed code.
     */
    public const SENTINELS = [
        'Drupal\Core\Action\Attribute\Action' => 'entity:save_action',
        'Drupal\Core\Archiver\Attribute\Archiver' => 'Tar',
        'Drupal\Core\Block\Attribute\Block' => 'broken',
        'Drupal\Core\Condition\Attribute\Condition' => 'request_path',
        'Drupal\Core\Entity\Attribute\EntityReferenceSelection' => 'broken',
        'Drupal\Core\Field\Attribute\FieldType' => 'string',
        'Drupal\Core\Field\Attribute\FieldFormatter' => 'string',
        'Drupal\Core\Field\Attribute\FieldWidget' => 'string_textfield',
        'Drupal\Core\Layout\Attribute\Layout' => 'layout_onecol',
        'Drupal\Core\Mail\Attribute\Mail' => 'php_mail',
        'Drupal\filter\Attribute\Filter' => 'filter_null',
        'Drupal\image\Attribute\ImageEffect' => 'image_scale',
        'Drupal\media\Attribute\MediaSource' => 'file',
        'Drupal\rest\Attribute\RestResource' => 'entity',
    ];

    private function __construct() {}

    /**
     * The attribute class a manager class, or a subclass of one, discovers.
     *
     * @return non-empty-string|null
     */
    public static function attributeFor(Codebase $codebase, string $manager): ?string
    {
        $known = self::lowercased();
        $attribute = $known[strtolower($manager)] ?? null;
        if ($attribute !== null) {
            return $attribute;
        }

        foreach ($codebase->getClassAncestors($manager) as $ancestor) {
            $attribute = $known[strtolower($ancestor)] ?? null;
            if ($attribute !== null) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * @return array<string, non-empty-string>
     */
    private static function lowercased(): array
    {
        /** @var array<string, non-empty-string>|null $table */
        static $table = null;
        if ($table === null) {
            $table = [];
            foreach (self::ATTRIBUTES as $manager => $attribute) {
                $table[strtolower($manager)] = $attribute;
            }
        }

        return $table;
    }
}
