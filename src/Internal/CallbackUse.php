<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Reporting\Level;

/**
 * How core runs the callbacks under one render or form array key.
 *
 * @internal
 */
enum CallbackUse
{
    /**
     * `Renderer::doCallback()`: the callable resolver, then
     * `doTrustedCallback()` with `RenderCallbackInterface` as the extra
     * trusted interface. It throws for an untrusted callback.
     */
    case Render;

    /**
     * `doTrustedCallback()` on a PHP callable without the resolver, as the
     * date elements and the component element run theirs. It throws for an
     * untrusted callback and trusts no extra interface.
     */
    case TrustedCallable;

    /**
     * `FormState::prepareCallback()`, which turns `'::method'` into a method
     * of the form object, then the callable resolver.
     */
    case Form;

    /**
     * `FormBuilder` checks `is_callable()` and falls back to the default
     * value callback without a word when it fails.
     */
    case Value;

    /**
     * A plain PHP call without the resolver or a trust check:
     * `call_user_func()` for `#machine_name['exists']`, `$callback()` for
     * `#file_value_callbacks`. It throws for anything PHP cannot call.
     */
    case Direct;

    public const RENDER_CALLBACK = 'Drupal\Core\Render\Element\RenderCallbackInterface';

    /**
     * The interface that makes every method of an implementing class trusted.
     */
    public function trustedInterface(): ?string
    {
        return $this === self::Render ? self::RENDER_CALLBACK : null;
    }

    public function checksTrust(): bool
    {
        return $this === self::Render || $this === self::TrustedCallable;
    }

    /**
     * Whether core passes the callback through the callable resolver. The
     * resolver instantiates the class for a `'Class::method'` string naming
     * an instance method, and runs a string without a colon that names a
     * service through the service's `__invoke()`. `is_callable()` and a
     * `callable` parameter do neither.
     */
    public function resolves(): bool
    {
        return $this === self::Render || $this === self::Form;
    }

    /**
     * Whether a plain function name is checked. On the render, date and
     * component keys `doTrustedCallback()` rejects every one of them, and
     * the `drupal/render-callback` lint rule reports those written in an
     * array literal.
     */
    public function checksFunctions(): bool
    {
        return $this === self::Form || $this === self::Value || $this === self::Direct;
    }

    /**
     * Whether `'::method'` names a method of the form object. Only
     * `FormState::prepareCallback()` turns it into one; everywhere else it
     * names no class.
     */
    public function hasFormObject(): bool
    {
        return $this === self::Form;
    }

    /**
     * Error where core throws, Warning where it skips the callback silently.
     */
    public function level(): Level
    {
        return $this === self::Value ? Level::Warning : Level::Error;
    }
}
