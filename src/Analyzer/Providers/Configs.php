<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\ClassNames;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * Shared helpers for the config providers.
 *
 * @internal
 */
final class Configs
{
    /**
     * Where `get()` is declared. Method targets match on the declaring class,
     * so the subclasses would never match.
     */
    public const BASE = 'Drupal\Core\Config\ConfigBase';

    public const EDITABLE = 'Drupal\Core\Config\Config';

    public const IMMUTABLE = 'Drupal\Core\Config\ImmutableConfig';

    public const FACTORY = 'Drupal\Core\Config\ConfigFactoryInterface';

    /**
     * `config()` is declared on the trait, not on `ConfigFormBase`, and
     * targets match on the declaring class.
     */
    public const FORM_TRAIT = 'Drupal\Core\Form\ConfigFormBaseTrait';

    private function __construct() {}

    /**
     * The calls that hand out a config object by name.
     *
     * @return non-empty-list<MethodTarget>
     */
    public static function loaders(): array
    {
        return [
            MethodTarget::exact(self::FACTORY, 'get'),
            MethodTarget::exact(self::FACTORY, 'getEditable'),
            MethodTarget::exact('Drupal', 'config'),
            MethodTarget::exact(self::FORM_TRAIT, 'config'),
        ];
    }

    /**
     * A config object type tagged with its config name, for example
     * `ImmutableConfig<'system.site'>`, so `->get()` on it knows which schema
     * to consult.
     */
    public static function tagged(string $class, string $name): Type
    {
        return Type::namedObject($class, Type::literalString($name));
    }

    /**
     * The config name a receiver was tagged with, or null for an untagged or
     * ambiguous receiver.
     */
    public static function nameOf(?Type $receiver): ?string
    {
        return Types::agreed($receiver, self::taggedName(...));
    }

    /**
     * The name one config object was tagged with.
     */
    private static function taggedName(NamedObjectType $atomic): ?string
    {
        if (!ClassNames::anyIs([$atomic->name], [self::EDITABLE, self::IMMUTABLE])) {
            return null;
        }

        return ($atomic->parameters[0] ?? null)?->getLiteralString();
    }
}
