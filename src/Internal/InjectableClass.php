<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_pop;
use function in_array;
use function preg_match_all;
use function strrchr;
use function substr;

/**
 * Tells whether a class can get services injected, as Coder's
 * DrupalPractice standard decides it.
 *
 * @internal
 */
final class InjectableClass
{
    /**
     * The base classes that Coder's GlobalDrupal sniff lists. A class that
     * extends one of them can get services injected.
     */
    private const BASE_CLASSES = [
        'BlockBase',
        'ConfigFormBase',
        'ContentEntityForm',
        'ControllerBase',
        'EntityForm',
        'EntityReferenceFormatterBase',
        'FileFormatterBase',
        'FormatterBase',
        'FormBase',
        'ImageFormatter',
        'ImageFormatterBase',
        'WidgetBase',
    ];

    private function __construct() {}

    /**
     * Whether the class extends one of the base classes, implements
     * `ContainerInjectionInterface`, or is a service of its module.
     */
    public static function check(SourceFile $file, Node $class): bool
    {
        // Coder compares the names as written. This compares the last part,
        // so `\Drupal\Core\Form\FormBase` counts too.
        $parent = Nodes::clauseNames($file, $class, NodeKind::Extends)[0] ?? null;
        if ($parent !== null && in_array(self::shortName($parent), self::BASE_CLASSES, strict: true)) {
            return true;
        }

        foreach (Nodes::clauseNames($file, $class, NodeKind::Implements) as $interface) {
            if (self::shortName($interface) === 'ContainerInjectionInterface') {
                return true;
            }
        }

        $name = self::className($file, $class);

        return $name !== null && ServiceClasses::has($file->path, $name);
    }

    /**
     * The fully qualified name of the class, with the last `namespace`
     * statement above it, as Coder builds it. A class outside a namespace is
     * never a service for Coder.
     */
    private static function className(SourceFile $file, Node $class): ?string
    {
        $matches = [];
        preg_match_all(
            '/^[ \t]*namespace[ \t]+([\w\\\\]+)[ \t]*[;{]/m',
            substr($file->contents, offset: 0, length: $class->span->start),
            $matches,
        );
        $namespace = array_pop($matches[1]);
        $name = Nodes::declaredName($file, $class);

        return $namespace === null || $name === null ? null : $namespace . '\\' . $name;
    }

    /**
     * The last part of a class name as written, such as `FormBase` for
     * `\Drupal\Core\Form\FormBase`.
     */
    private static function shortName(string $name): string
    {
        $last = strrchr($name, needle: '\\');

        return $last === false ? $name : substr($last, offset: 1);
    }
}
