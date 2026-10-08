<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\HookFunctions;
use PHPUnit\Framework\TestCase;

use function dirname;
use function serialize;
use function unserialize;

final class HookFunctionsTest extends TestCase
{
    public function testReadsDeprecationAndParameterCountsFromApiFiles(): void
    {
        $hooks = HookFunctions::fromApiFiles([dirname(__DIR__) . '/fixtures/api/sample.api.php']);

        self::assertSame(6, $hooks->count());
        self::assertNull($hooks->deprecation('hook_form_alter'));
        self::assertSame(3, $hooks->parameterCount('hook_form_alter'));
        self::assertSame(3, $hooks->parameterCount('hook_with_defaults'));
        self::assertSame(0, $hooks->parameterCount('hook_bare'));
        self::assertSame(
            ['HOOK_OLD', 'in drupal:11.1.0 and is removed from drupal:12.0.0. Use hook_bare() instead.'],
            $hooks->deprecation('HOOK_OLD'),
        );
        self::assertSame(1, $hooks->parameterCount('hook_old'));
        self::assertNull($hooks->deprecation('hook_unknown'));
        self::assertNull($hooks->parameterCount('_api_helper'));
        // A docblock ends at its own `*/`: the helper's @deprecated stays with
        // the helper.
        self::assertNull($hooks->deprecation('hook_after_helper'));
        // Core puts `// phpcs:` lines between some docblocks and functions.
        self::assertNull($hooks->deprecation('hook_update_N'));
        self::assertSame(1, $hooks->parameterCount('hook_update_N'));
    }

    public function testMatchesNamesWithPlaceholders(): void
    {
        $hooks = HookFunctions::fromApiFiles([dirname(__DIR__) . '/fixtures/api/placeholders.api.php']);
        $tag = ['hook_thing_query_TAG_alter', 'in thing:1.2.0 and is removed from thing:2.0.0. Use the event instead.'];

        self::assertSame($tag, $hooks->deprecation('hook_thing_query_foo_alter'));
        self::assertSame($tag, $hooks->deprecation('HOOK_THING_QUERY_FOO_BAR_ALTER'));
        // The disk cache keeps the patterns along with the hooks.
        /** @var mixed $cached */
        $cached = unserialize(serialize($hooks), ['allowed_classes' => [HookFunctions::class]]);
        self::assertInstanceOf(HookFunctions::class, $cached);
        self::assertSame($tag, $cached->deprecation('hook_thing_query_foo_alter'));
        // A placeholder stands for at least one character.
        self::assertNull($hooks->deprecation('hook_thing_query_alter'));
        self::assertNull($hooks->deprecation('hook_thing_query__alter'));
        // The exact name decides, deprecated or not.
        self::assertNull($hooks->deprecation('hook_thing_query_all_alter'));
        self::assertSame(
            [
                'hook_form_old_alter',
                'in drupal:11.1.0 and is removed from drupal:12.0.0. Use hook_form_alter() instead.',
            ],
            $hooks->deprecation('hook_form_old_alter'),
        );
        self::assertNull($hooks->deprecation('hook_form_user_form_alter'));
        self::assertSame(
            'hook_field_widget_WIDGET_TYPE_form_alter',
            $hooks->deprecation('hook_field_widget_string_textfield_form_alter')[0] ?? null,
        );
        // The current single element hook matches this name too, with more
        // text outside its placeholder.
        self::assertNull($hooks->deprecation('hook_field_widget_single_element_string_textfield_form_alter'));
        // The tag hook has more text outside its placeholder than the entity
        // view alter hook, so it decides.
        self::assertSame(
            'hook_thing_query_TAG_alter',
            $hooks->deprecation('hook_thing_query_node_view_alter')[0] ?? null,
        );
        // Both match with as much text outside the placeholder, and one is
        // not deprecated.
        self::assertNull($hooks->deprecation('hook_thing_query_query_alter'));
        // Neighbouring uppercase segments form one placeholder, and the
        // double underscores between placeholders stay literal.
        self::assertSame(
            'hook_thing_tag__ENTITY_TYPE__TAG_alter',
            $hooks->deprecation('hook_thing_tag__node__search_alter')[0] ?? null,
        );
        self::assertNull($hooks->deprecation('hook_thing_tag_node_search_alter'));
        // The parameter count is read for the exact name only.
        self::assertNull($hooks->parameterCount('hook_thing_query_foo_alter'));
        self::assertSame(1, $hooks->parameterCount('hook_thing_query_TAG_alter'));
    }

    public function testReadsPlaceholdersFromDefinitionNames(): void
    {
        $hooks = HookFunctions::fromDefinitions([
            'hook_ENTITY_TYPE_old' => ['in drupal:11.1.0 and is removed from drupal:12.0.0.', 1],
            'hook_node_old' => [null, 1],
        ]);

        self::assertSame('hook_ENTITY_TYPE_old', $hooks->deprecation('hook_user_old')[0] ?? null);
        self::assertNull($hooks->deprecation('hook_node_old'));
    }

    public function testSkipsUnreadableFiles(): void
    {
        self::assertSame(0, HookFunctions::fromApiFiles([dirname(__DIR__) . '/fixtures/api/missing.api.php'])->count());
    }
}
