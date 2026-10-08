<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;

/**
 * One callback written under a render or form array key.
 *
 * @internal
 *
 * @mago-expect lint:excessive-parameter-list
 */
final class ElementCallback
{
    /**
     * @param string $key The array key, such as `#submit`.
     * @param Node $value The callback value, where a report goes.
     * @param string $name The method or function it names, an identifier.
     * @param non-empty-string|null $class The class it names, fully
     *   qualified without the leading backslash. For `self`, `static` and
     *   `$this` it is the class the code is written in, and for `'::method'`
     *   the named class the code is written in, if any.
     * @param bool $late Whether the class is only known at run time and may
     *   be a subclass of $class, as for `static::class` and `$this`.
     * @param Node|null $object The expression whose type gives the class, for
     *   `[$object, 'method']` other than `$this`.
     * @param bool $inHook Whether the method a `'::method'` callback is
     *   written in carries `#[Hook]`. Not read for the other styles.
     */
    public function __construct(
        public readonly string $key,
        public readonly CallbackUse $use,
        public readonly Node $value,
        public readonly CallStyle $style,
        public readonly string $name,
        public readonly ?string $class = null,
        public readonly bool $late = false,
        public readonly ?Node $object = null,
        public readonly bool $inHook = false,
    ) {}
}
