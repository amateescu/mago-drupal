<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function array_keys;
use function explode;
use function file_get_contents;
use function in_array;
use function json_decode;
use function preg_match_all;
use function sort;
use function str_contains;
use function str_starts_with;
use function strstr;

use const JSON_THROW_ON_ERROR;

/**
 * The map in `tests/phpstan-drupal-map.json`. It lists every rule and
 * extension that phpstan-drupal registers, and what handles each of them.
 *
 * A rule lists its handlers in `where`, and an identifier has a `where` of
 * its own when it goes elsewhere. A handler is `check:<analyzer check code>`,
 * `ext:<rule code>`, `analyze:<issue>`, `doc:<page>#<anchor>` or `none`. A
 * note is kept only where no entry of this extension ports the check, because
 * an entry's "Compared with phpstan-drupal" note says how it differs.
 *
 * A rule's `default` is `off` when phpstan-drupal turns it off by default,
 * and `manual` when it runs only once a project adds it to `rules`.
 *
 * @phpstan-type Identifier array{identifier: string, status: string, where?: list<string>, note?: string}
 * @phpstan-type Rule array{rule: string, default?: string, where: list<string>, note?: string, identifiers: list<Identifier>}
 * @phpstan-type Extension array{extension: string, default?: string, status: string, where: list<string>, note?: string}
 */
final class PHPStanDrupalMap
{
    /**
     * @param list<Rule> $rules
     * @param list<Extension> $extensions
     */
    private function __construct(
        public readonly string $version,
        public readonly array $rules,
        public readonly array $extensions,
    ) {}

    public static function load(string $path): self
    {
        /** @var array{version: string, rules: list<Rule>, extensions: list<Extension>} $map */
        $map = json_decode(json: (string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);

        return new self($map['version'], $map['rules'], $map['extensions']);
    }

    /**
     * Returns the handlers of an identifier: its own, or else the rule's.
     *
     * @param Rule $rule
     * @param Identifier $identifier
     *
     * @return list<string>
     */
    public static function where(array $rule, array $identifier): array
    {
        return $identifier['where'] ?? $rule['where'];
    }

    /**
     * Returns the phpstan-drupal rules that the map sends to a check or a rule
     * of this extension, sorted.
     *
     * @return list<string>
     */
    public function ports(string $code): array
    {
        $ports = [];
        foreach ($this->rules as $rule) {
            foreach ($rule['identifiers'] as $identifier) {
                $where = self::where($rule, $identifier);
                if (in_array("check:{$code}", $where, strict: true) || in_array("ext:{$code}", $where, strict: true)) {
                    $ports[$rule['rule']] = true;
                }
            }
        }

        $names = array_keys($ports);
        sort($names);

        return $names;
    }

    /**
     * Returns the phpstan-drupal rules that the Ports line of an entry names,
     * sorted. The line names them as "phpstan-drupal's `RuleName`", and a list
     * below it can name more.
     *
     * @return list<string>
     */
    public static function named(string $body): array
    {
        $names = [];
        $inPorts = false;
        foreach (explode(separator: "\n", string: $body) as $line) {
            if (str_starts_with($line, '- **')) {
                $inPorts = str_starts_with($line, '- **Ports:**');
            } elseif (!str_starts_with($line, '  ')) {
                $inPorts = false;
            }

            if (!$inPorts || !str_contains($line, "phpstan-drupal's")) {
                continue;
            }

            $matches = [];
            preg_match_all('/`([A-Z][A-Za-z]+)`/', (string) strstr($line, needle: "phpstan-drupal's"), $matches);
            foreach ($matches[1] as $name) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }
}
