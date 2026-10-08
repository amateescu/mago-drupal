<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Type;

use function array_key_exists;
use function array_slice;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;
use function str_starts_with;

/**
 * The kinds of value the `parameters:` sections of the services files give
 * each container parameter.
 *
 * Only the kind is kept, never the value: `settings.php`, a site's own
 * services file and service providers change values, and `app.root` and
 * `site.path` are empty strings the kernel replaces. A name that several
 * files define gets every kind they give it.
 *
 * @internal
 */
final class ServiceParameters
{
    private const STRING = 'string';

    private const BOOL = 'bool';

    private const INT = 'int';

    private const FLOAT = 'float';

    private const ARRAY = 'array';

    /**
     * A null, or a value the container resolves at runtime, such as a
     * `%name%` reference to another parameter, a `'@id'` service reference
     * or a custom tag. Any such kind leaves the declared type in place.
     */
    private const OTHER = 'other';

    /**
     * A string that is one `%name%` placeholder and nothing else. The
     * container replaces it with the other parameter's value as it is, of
     * whatever kind. A placeholder inside a longer string makes a string.
     */
    private const REFERENCE = '/^%[^%\s]+%$/';

    /**
     * @param array<non-empty-string, array<string, true>> $kinds
     */
    public function __construct(
        private readonly array $kinds = [],
    ) {}

    /**
     * The kind of each parameter one parsed services file defines.
     *
     * @return array<non-empty-string, string>
     */
    public static function kindsIn(mixed $document): array
    {
        $kinds = [];
        /** @var mixed $value */
        foreach (Shape::arrayAt($document, 'parameters') as $name => $value) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $kinds[$name] = self::kindOf($value);
        }

        return $kinds;
    }

    /**
     * The type `getParameter()` returns for the name, or null when a file
     * gives no kind the type can say, or no file defines the name.
     */
    public function type(string $name): ?Type
    {
        $kinds = $this->kinds[$name] ?? [];
        if ($kinds === [] || array_key_exists(self::OTHER, $kinds)) {
            return null;
        }

        $types = [];
        foreach ($kinds as $kind => $_) {
            $types[] = match ($kind) {
                self::STRING => Type::string(),
                self::BOOL => Type::bool(),
                self::INT => Type::int(),
                self::FLOAT => Type::float(),
                // A site's file or a provider may add or drop keys, so the
                // shape of the value in YAML says nothing about them.
                default => Type::array(Type::union(Type::int(), Type::string()), Type::mixed()),
            };
        }

        return count($types) === 1 ? $types[0] : Type::union($types[0], $types[1], ...array_slice($types, offset: 2));
    }

    private static function kindOf(mixed $value): string
    {
        return match (true) {
            is_string($value) => self::isReference($value) ? self::OTHER : self::STRING,
            is_bool($value) => self::BOOL,
            is_int($value) => self::INT,
            is_float($value) => self::FLOAT,
            is_array($value) => self::ARRAY,
            default => self::OTHER,
        };
    }

    /**
     * Whether the loader turns the string into something else: `'@id'` into
     * a service reference, or a lone `%name%` into the other parameter's
     * value. `'@@'` escapes a leading `@` and leaves a string.
     */
    private static function isReference(string $value): bool
    {
        return (
            str_starts_with($value, '@') && !str_starts_with($value, '@@')
            || preg_match(self::REFERENCE, $value) === 1
        );
    }
}
