<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use Mago\Sdk\Linter\RuleDefinition;

use function explode;
use function implode;
use function ksort;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function str_starts_with;
use function strtolower;

use const PREG_SET_ORDER;

/**
 * The entry of one rule on a rule page: a `## drupal/<code>` heading, a list
 * of facts, and the description.
 */
final class RuleEntry
{
    public function __construct(
        public readonly string $code,
        public readonly string $body,
    ) {}

    /**
     * Returns how the facts list differs from the rule and the parity map.
     *
     * @param RuleDefinition $withCore The rule's definition when the worker
     *   runs with `--core`.
     * @param array<string, bool> $ports The codes that the map says the rule
     *   ports, each mapped to whether the port is partial.
     *
     * @return list<string>
     */
    public function problems(RuleDefinition $rule, RuleDefinition $withCore, array $ports): array
    {
        $facts = $this->facts();
        $problems = [];
        $offWithCore = !$withCore->defaultEnabled;

        $level = strtolower($rule->defaultLevel->name);
        if (preg_match('/^- \*\*Level:\*\* (\w+)$/m', $facts, $match) !== 1 || $match[1] !== $level) {
            $problems[] = "{$this->code}: the level is {$level}.";
        }

        if (preg_match('/^- \*\*Fix:\*\* \S/m', $facts) !== 1) {
            $problems[] = "{$this->code}: the entry has no Fix line.";
        }

        if (str_contains($facts, '- **Off with `--core`**') !== $offWithCore) {
            $problems[] = $offWithCore
                ? "{$this->code}: --core turns the rule off."
                : "{$this->code}: --core does not turn the rule off.";
        }

        $listed = $this->ports();
        if ($listed !== $ports) {
            $problems[] =
                "{$this->code}: the Ports line differs from tests/coder-map.json. The map has "
                . self::names($ports)
                . ', and the entry has '
                . self::names($listed)
                . '.';
        }

        return $problems;
    }

    /**
     * Returns the Coder codes that the Ports line names, each mapped to
     * whether it says "(partly)".
     *
     * @return array<string, bool>
     */
    public function ports(): array
    {
        $ports = [];
        foreach (explode(separator: "\n", string: $this->facts()) as $line) {
            $port = [];
            if (
                preg_match(
                    '/^\s*- (?:\*\*Ports:\*\* )?`([A-Z][A-Za-z0-9]*(?:\.[A-Za-z0-9]+){2,3})`(.*)$/',
                    $line,
                    $port,
                ) !== 1
            ) {
                continue;
            }

            // A sniff that gives several codes is written once, followed by
            // its codes, as in "`Sniff`: `A`, `B` (partly)".
            if (!str_starts_with($port[2], ': ')) {
                $ports[$port[1]] = str_contains($port[2], '(partly)');

                continue;
            }

            $codes = [];
            preg_match_all('/`(\w+)`( \(partly\))?/', $port[2], $codes, PREG_SET_ORDER);
            foreach ($codes as $code) {
                $ports[$port[1] . '.' . $code[1]] = ($code[2] ?? '') !== '';
            }
        }

        ksort($ports);

        return $ports;
    }

    /**
     * Returns the facts list, which ends at the first blank line.
     */
    private function facts(): string
    {
        return explode(separator: "\n\n", string: ltrim($this->body, characters: "\n"))[0];
    }

    /**
     * @param array<string, bool> $ports
     */
    private static function names(array $ports): string
    {
        $names = [];
        foreach ($ports as $name => $partial) {
            $names[] = $partial ? "{$name} (partly)" : $name;
        }

        return $names === [] ? 'none' : implode(', ', $names);
    }
}
