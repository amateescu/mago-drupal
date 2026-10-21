<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Traits;

use function array_key_exists;

/**
 * The classes that use a trait (see TraitComposers), with what is needed to
 * tell whether they still do after some files changed.
 *
 * The scan behind them reads only the declarations of the files that name the
 * trait or one of the traits that use it. A file that doesn't name any of them,
 * before or after a change, can't change the result, and an edit rarely names
 * one. So the earlier result is kept unless a changed, added or removed file
 * names one of the traits, and the scan runs again only then.
 *
 * @internal
 */
final class TraitComposerScan
{
    /**
     * @param non-empty-string $trait
     * @param array<string, string|null> $stamps Each file's stamp at the
     *   scan (see DiskCache::stamps()).
     * @param array<string, true> $walked The files that named one of the
     *   traits.
     */
    private function __construct(
        private readonly string $trait,
        public readonly TraitComposers $composers,
        private readonly array $stamps,
        private readonly array $walked,
    ) {}

    /**
     * The composers of the trait in the files as they are now: the earlier
     * result while it holds, or a new scan.
     *
     * @param list<string> $files
     * @param array<string, string|null> $stamps Each file's stamp now.
     * @param non-empty-string $trait
     */
    public static function update(?self $earlier, array $files, array $stamps, string $trait): self
    {
        if ($earlier !== null && $earlier->trait === $trait && $earlier->holds($stamps)) {
            return new self($trait, $earlier->composers, $stamps, $earlier->walked);
        }

        [$composers, $walked] = TraitComposers::scan($files, $trait);

        return new self($trait, $composers, $stamps, $walked);
    }

    /**
     * Whether the files that changed, came or went don't name any of the
     * traits, before or now. A file with a null stamp counts as changed.
     *
     * @param array<string, string|null> $stamps
     */
    private function holds(array $stamps): bool
    {
        foreach ($stamps as $path => $stamp) {
            if ($stamp !== null && ($this->stamps[$path] ?? null) === $stamp) {
                continue;
            }

            if (array_key_exists($path, $this->walked) || $this->composers->namedIn($path, $this->trait)) {
                return false;
            }
        }

        foreach ($this->walked as $path => $_) {
            if (!array_key_exists($path, $stamps)) {
                return false;
            }
        }

        return true;
    }
}
