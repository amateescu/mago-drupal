<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function file_get_contents;
use function in_array;
use function json_decode;
use function ksort;

use const JSON_THROW_ON_ERROR;

/**
 * The parity map in `tests/coder-map.json`: every sniff of Coder's `Drupal`
 * and `DrupalPractice` standards, and what handles each of its codes.
 *
 * A sniff lists its handlers in `where`, and a code has a `where` of its own
 * when it goes elsewhere. A handler is `format`, `analyze`, `analyze:<issue>`,
 * `ext:<rule code>`, `lint:<rule>`, `branch:<name>`, `phpcs` or `none`. A
 * note is kept only where no rule of this extension ports the check, because
 * a rule's docs entry describes how it differs from Coder.
 *
 * @phpstan-type Code array{code: string, status: string, where?: list<string>, note?: string}
 * @phpstan-type Sniff array{sniff: string, standard: string, drupal7?: bool, where: list<string>, note?: string, codes: list<Code>}
 */
final class CoderMap
{
    /**
     * @param list<Sniff> $sniffs
     */
    private function __construct(
        public readonly array $sniffs,
    ) {}

    public static function load(string $path): self
    {
        /** @var list<Sniff> $sniffs */
        $sniffs = json_decode(json: (string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);

        return new self($sniffs);
    }

    /**
     * Returns the handlers of a code: its own, or else the sniff's.
     *
     * @param Sniff $sniff
     * @param Code $code
     *
     * @return list<string>
     */
    public static function where(array $sniff, array $code): array
    {
        return $code['where'] ?? $sniff['where'];
    }

    /**
     * Returns the codes that a rule ports, as `Sniff.Code` mapped to whether
     * the port is partial.
     *
     * @return array<string, bool>
     */
    public function ports(string $rule): array
    {
        $ports = [];
        foreach ($this->sniffs as $sniff) {
            foreach ($sniff['codes'] as $code) {
                if (!in_array('ext:' . $rule, self::where($sniff, $code), strict: true)) {
                    continue;
                }

                $ports[$sniff['sniff'] . '.' . $code['code']] = $code['status'] === 'partial';
            }
        }

        ksort($ports);

        return $ports;
    }
}
