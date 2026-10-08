<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function basename;
use function file_get_contents;
use function in_array;
use function is_file;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function str_starts_with;

/**
 * The names some code or config under the Drupal root defines a field with.
 *
 * The set is not split by entity type, so a field of one entity type counts
 * on every entity.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class FieldNames
{
    /**
     * The Field UI's default prefix, which the fields a site adds in its
     * active config have. That config is not read.
     */
    private const FIELD_UI_PREFIX = 'field_';

    /**
     * Keys `EntityType` fills in with their own names, so no `entity_keys`
     * lists them, and whose fields `ContentEntityBase` creates.
     */
    private const DEFAULT_KEYS = ['default_langcode', 'revision_default', 'revision_translation_affected'];

    /**
     * A string key given a field or field storage definition, as in
     * `baseFieldDefinitions()` and the field info hooks, set one at a time
     * or in an array literal. Typed data definitions such as
     * `DataDefinition` name field properties, not fields.
     */
    private const DEFINITION = '/[\'"]([a-z_][a-z0-9_]*)[\'"]\s*(?:\]\s*=|=>)\s*\\\\?(?:\w+\\\\)*\w*Field(?:Storage)?Definition::create/';

    /**
     * A field name in the values of `FieldStorageConfig::create()` and
     * `FieldConfig::create()`.
     */
    private const CONFIG_VALUE = '/[\'"]field_name[\'"]\s*=>\s*[\'"]([a-z_][a-z0-9_]*)[\'"]/';

    /**
     * A field name tests keep in a variable or property, such as
     * `$field_name = 'images'` or `$this->fieldName = 'test_field'`, and
     * then create the field and read `$entity->{$field_name}` with.
     */
    private const VARIABLE = '/\$(?:this->)?(?i:field_?name)\w*\s*=\s*[\'"]([a-z_][a-z0-9_]*)[\'"]/';

    /**
     * The field name test helpers such as `createFileField()` and
     * `createImageField()` take first.
     */
    private const HELPER = '/->create\w*Field\(\s*[\'"]([a-z_][a-z0-9_]*)[\'"]/';

    /**
     * A field storage an update hook installs.
     */
    private const INSTALLED = '/installFieldStorageDefinition\(\s*[\'"]([a-z_][a-z0-9_]*)[\'"]/';

    /**
     * The entity keys of an entity type attribute, annotation or definition
     * array. The fields of the id, uuid, revision, bundle and langcode keys
     * come from `ContentEntityBase`, and the owner, published and revision
     * metadata ones from traits, all under the names the keys give.
     */
    private const KEYS = '/(?:entity_keys|revision_metadata_keys)[\'"]?\s*(?:=>|[:=])\s*[\[{]([^\]}]*)[\]}]/';

    private const KEY_VALUE = '/(?:=>|=)\s*[\'"]([a-z_][a-z0-9_]*)[\'"]/';

    /**
     * `field.storage.<entity type>.<name>.yml` and
     * `field.field.<entity type>.<bundle>.<name>.yml`.
     */
    private const CONFIG_FILE = '/^field\.(?:storage\.[^.]+|field\.[^.]+\.[^.]+)\.(?<name>[a-z_][a-z0-9_]*)\.yml$/';

    /**
     * @param array<string, true> $names
     */
    private function __construct(
        private readonly array $names,
    ) {}

    /**
     * Reads the names off PHP source and the file names of field config.
     *
     * @param list<string> $sources
     * @param list<string> $configs
     */
    public static function fromFiles(array $sources, array $configs): self
    {
        $names = [];
        foreach ($sources as $source) {
            $contents = is_file($source) ? file_get_contents($source) : false;
            if ($contents === false) {
                continue;
            }

            $names = self::read($contents, $names);
        }

        foreach ($configs as $config) {
            $matches = [];
            if (preg_match(self::CONFIG_FILE, basename($config), $matches) === 1) {
                $names[$matches['name']] = true;
            }
        }

        return new self($names);
    }

    /**
     * Whether the name is a field on some entity. With no field found at
     * all, as when no Drupal root was found, every name counts.
     */
    public function has(string $name): bool
    {
        return (
            $this->names === []
            || array_key_exists($name, $this->names)
            || str_starts_with($name, self::FIELD_UI_PREFIX)
            || in_array($name, self::DEFAULT_KEYS, strict: true)
        );
    }

    /**
     * Adds the names one file defines.
     *
     * @param array<string, true> $names
     * @return array<string, true>
     */
    private static function read(string $contents, array $names): array
    {
        // Each check skips a file without the text its pattern needs, which
        // is most files.
        foreach ([
            'Definition::create' => self::DEFINITION,
            'field_name' => self::CONFIG_VALUE,
            'ield' => self::VARIABLE,
            'Field(' => self::HELPER,
            'installFieldStorageDefinition' => self::INSTALLED,
        ] as $needle => $pattern) {
            $matches = [];
            if (!str_contains($contents, $needle) || preg_match_all($pattern, $contents, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        $blocks = [];
        if (!str_contains($contents, '_keys') || preg_match_all(self::KEYS, $contents, $blocks) === 0) {
            return $names;
        }

        foreach ($blocks[1] as $keys) {
            $values = [];
            preg_match_all(self::KEY_VALUE, $keys, $values);
            foreach ($values[1] as $name) {
                $names[$name] = true;
            }
        }

        return $names;
    }
}
