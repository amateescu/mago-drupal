<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Cache;

use Closure;
use Mago\Sdk\Syntax\SourceFile;
use WeakMap;

use function array_key_exists;

/**
 * Values built for a file, so each piece of per-file work runs once.
 *
 * Each caller keeps its own memo. The memo holds the values of its last key,
 * which is what the rules of one file need, since they run in a row. A value
 * is either the one value for the key, or one of several items, such as the
 * value for a node or for the docblock at an offset.
 *
 * The key is what the values are built from. For values built from the text
 * alone it is the file's text. A string compare is a pointer check for the
 * string of the same request, and two files almost always differ in length
 * or in their first bytes. An editor session lints the same path again after
 * every edit, so the path is the key only for values built from the path.
 *
 * For values that read nodes, resolved names or the target list, the key is
 * the snapshot. A snapshot holds only the subtrees its targets need, so two
 * snapshots of the same text can hold different nodes, and an analyzer
 * snapshot can come without its text. The SDK decodes a new snapshot for
 * every request and never changes one, and the memo holds its last key, so
 * PHP doesn't give that object's handle to a new snapshot. A snapshot's
 * identity is a safe key.
 *
 * An analyzer hook that waits on the host lets other files' requests run in
 * between, so a hook's memo calls keepingSnapshots().
 *
 * get() takes a closure that builds the value. A closure costs an allocation
 * per call, so code that runs for every node calls has(), value() and keep()
 * instead.
 *
 * @internal
 *
 * @template T
 */
final class FileMemo
{
    /**
     * The item that holds the one value for a key.
     */
    private const VALUE = 0;

    private string|SourceFile|null $key = null;

    /**
     * The values of the last key, by item.
     *
     * @var array<array-key, T>
     */
    private array $values = [];

    /**
     * The values of the other snapshots that still exist, once
     * keepingSnapshots() is called.
     *
     * @var WeakMap<SourceFile, array<array-key, T>>|null
     */
    private ?WeakMap $snapshots = null;

    /**
     * Makes the memo also keep the values of every other snapshot that
     * still exists, so another file's request doesn't push them out.
     *
     * A value must not hold its snapshot. PHP 8.1 and 8.2 never drop a weak
     * map entry whose value holds its key, so every snapshot the worker saw
     * would stay in memory.
     *
     * @return $this
     */
    public function keepingSnapshots(): self
    {
        $this->snapshots ??= new WeakMap();

        return $this;
    }

    /**
     * The value for the key, built again only for another key.
     *
     * @param string|SourceFile $key The file's text, or its snapshot.
     * @param Closure(): T $build
     *
     * @return T
     */
    public function get(string|SourceFile $key, Closure $build): mixed
    {
        return $this->has($key) ? $this->values[self::VALUE] : $this->keep($key, $build());
    }

    /**
     * Whether the memo holds the item for the key. The key becomes the last
     * key, which value() reads.
     *
     * @param string|SourceFile $key The file's text, or its snapshot.
     */
    public function has(string|SourceFile $key, int|string $item = self::VALUE): bool
    {
        if ($this->key !== $key) {
            $this->switchTo($key);
        }

        return array_key_exists($item, $this->values);
    }

    /**
     * The item of the last key. Call it right after has() returns true.
     *
     * @return T
     */
    public function value(int|string $item = self::VALUE): mixed
    {
        return $this->values[$item];
    }

    /**
     * Keeps the item for the key.
     *
     * @param string|SourceFile $key The file's text, or its snapshot.
     * @param T $value
     *
     * @return T
     */
    public function keep(string|SourceFile $key, mixed $value, int|string $item = self::VALUE): mixed
    {
        // A hook that waits on the host while it builds the value lets
        // another file's request run in between, and that request may have
        // switched the memo.
        if ($this->key !== $key) {
            $this->switchTo($key);
        }

        $this->values[$item] = $value;

        return $value;
    }

    private function switchTo(string|SourceFile $key): void
    {
        if ($this->snapshots !== null && $this->key instanceof SourceFile) {
            $this->snapshots->offsetSet($this->key, $this->values);
        }

        $this->key = $key;
        $this->values = $key instanceof SourceFile ? $this->takeKept($key) : [];
    }

    /**
     * The values kept for the snapshot. They are taken out of the weak map,
     * so a write doesn't copy them.
     *
     * @return array<array-key, T>
     */
    private function takeKept(SourceFile $file): array
    {
        if ($this->snapshots === null || !$this->snapshots->offsetExists($file)) {
            return [];
        }

        $values = $this->snapshots->offsetGet($file);
        $this->snapshots->offsetUnset($file);

        return $values;
    }
}
