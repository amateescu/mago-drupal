<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\CallbackUse;
use amateescu\MagoDrupal\Internal\ElementCallbacks;
use Mago\Sdk\Reporting\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function preg_match;

final class ElementCallbacksTest extends TestCase
{
    /**
     * @return iterable<string, array{string, CallbackUse}>
     */
    public static function keys(): iterable
    {
        yield '#pre_render' => ['#pre_render', CallbackUse::Render];
        yield '#post_render' => ['#post_render', CallbackUse::Render];
        yield '#access_callback' => ['#access_callback', CallbackUse::Render];
        yield '#lazy_builder' => ['#lazy_builder', CallbackUse::Render];
        yield '#date_date_callbacks' => ['#date_date_callbacks', CallbackUse::TrustedCallable];
        yield '#date_time_callbacks' => ['#date_time_callbacks', CallbackUse::TrustedCallable];
        yield '#propsAlter' => ['#propsAlter', CallbackUse::TrustedCallable];
        yield '#slotsAlter' => ['#slotsAlter', CallbackUse::TrustedCallable];
        yield '#validate' => ['#validate', CallbackUse::Form];
        yield '#submit' => ['#submit', CallbackUse::Form];
        yield '#element_validate' => ['#element_validate', CallbackUse::Form];
        yield '#process' => ['#process', CallbackUse::Form];
        yield '#after_build' => ['#after_build', CallbackUse::Form];
        yield '#entity_builders' => ['#entity_builders', CallbackUse::Form];
        yield '#ajax' => ['#ajax', CallbackUse::Form];
        yield '#value_callback' => ['#value_callback', CallbackUse::Value];
        yield '#machine_name' => ['#machine_name', CallbackUse::Direct];
        yield '#file_value_callbacks' => ['#file_value_callbacks', CallbackUse::Direct];
    }

    /**
     * The file gate and the key table have to agree, or a file holding a key
     * the table knows is never read.
     */
    #[DataProvider('keys')]
    public function testTheGateLetsEveryKeyThrough(string $key, CallbackUse $use): void
    {
        self::assertSame($use, ElementCallbacks::use($key));
        self::assertSame(1, preg_match(ElementCallbacks::GATE, "\$form['{$key}'] = [];"));
        self::assertSame(1, preg_match(ElementCallbacks::GATE, "\"{$key}\" => []"));
    }

    public function testOtherKeysAreNotCallbackKeys(): void
    {
        self::assertNull(ElementCallbacks::use('#type'));
        self::assertNull(ElementCallbacks::use('callback'));
        self::assertSame(0, preg_match(ElementCallbacks::GATE, subject: "\$form['#submit_button'] = [];"));
        self::assertSame(0, preg_match(ElementCallbacks::GATE, subject: '// Adds a #submit handler.'));
    }

    /**
     * Only the renderer trusts an extra interface, and only the renderer and
     * the form API pass callbacks through the callable resolver.
     */
    public function testEachUseFollowsCore(): void
    {
        self::assertSame(CallbackUse::RENDER_CALLBACK, CallbackUse::Render->trustedInterface());
        self::assertNull(CallbackUse::TrustedCallable->trustedInterface());
        self::assertTrue(CallbackUse::Render->resolves());
        self::assertTrue(CallbackUse::Form->resolves());
        self::assertFalse(CallbackUse::TrustedCallable->resolves());
        self::assertFalse(CallbackUse::Value->resolves());
        self::assertFalse(CallbackUse::Direct->resolves());
        self::assertFalse(CallbackUse::Form->checksTrust());
        self::assertFalse(CallbackUse::Direct->checksTrust());
        self::assertFalse(CallbackUse::Render->checksFunctions());
        self::assertFalse(CallbackUse::TrustedCallable->checksFunctions());
        self::assertTrue(CallbackUse::Direct->checksFunctions());
        self::assertTrue(CallbackUse::Form->hasFormObject());
        self::assertFalse(CallbackUse::Value->hasFormObject());
        self::assertSame(Level::Warning, CallbackUse::Value->level());
        self::assertSame(Level::Error, CallbackUse::Form->level());
        self::assertSame(Level::Error, CallbackUse::Direct->level());
    }
}
