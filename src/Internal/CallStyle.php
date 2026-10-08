<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * How a callback value names what it calls.
 *
 * @internal
 */
enum CallStyle
{
    /**
     * `'::method'`, a method of the form object.
     */
    case FormObject;

    /**
     * `'function_name'`.
     */
    case Function;

    /**
     * `'Class::method'`, or `static::class . '::method'` and the like.
     */
    case ClassString;

    /**
     * `[Class::class, 'method']` or `['Class', 'method']`.
     */
    case ClassArray;

    /**
     * `[$object, 'method']`, `$this` included.
     */
    case ObjectArray;

    /**
     * Whether PHP calls the method statically.
     */
    public function isStatic(): bool
    {
        return $this === self::ClassString || $this === self::ClassArray;
    }
}
