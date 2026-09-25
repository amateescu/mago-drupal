<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Closure;
use Mago\Sdk\Analyzer\Codebase;

use function array_key_exists;

/**
 * Values computed from the codebase, kept for one analysis.
 *
 * A watch or editor session analyzes again in the same worker, so the values
 * are dropped once the codebase belongs to a new analysis.
 *
 * @internal
 *
 * @template T
 */
final class AnalysisMemo
{
    /**
     * @var array<string, T>
     */
    private array $values = [];

    private ?int $generation = null;

    /**
     * The value for the key, computed on first use.
     *
     * A codebase query suspends this request and another one can fill the
     * same key in between; both compute the same answer. A value computed
     * for an analysis that has since been replaced is returned, not kept.
     *
     * @param Closure(): T $compute
     *
     * @return T
     */
    public function get(Codebase $codebase, string $key, Closure $compute): mixed
    {
        $generation = AnalysisGeneration::of($codebase);
        if ($generation !== $this->generation) {
            $this->values = [];
            $this->generation = $generation;
        }

        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        $value = $compute();
        if ($this->generation === $generation) {
            $this->values[$key] = $value;
        }

        return $value;
    }
}
