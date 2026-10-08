<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * The PHP files one walk of the extension source directories found.
 *
 * @internal
 */
final class SourceFileSet
{
    /**
     * @param list<string> $candidates The directories the walk started from,
     *   in order, including the ones that did not exist.
     * @param list<string> $files Every `.php` file under them.
     * @param array<string, int> $directories Every directory the walk read or
     *   was asked to read, with its modification time, 0 for one that did not
     *   exist and -1 for one that may still be changing. A file or
     *   subdirectory added or removed changes it; an edited file does not.
     */
    public function __construct(
        public readonly array $candidates,
        public readonly array $files,
        public readonly array $directories,
    ) {}

    /**
     * @param list<string> $candidates
     */
    public static function of(array $candidates): self
    {
        [$files, $read] = ExtensionFiles::phpFileTree($candidates);
        foreach ($candidates as $candidate) {
            $read[$candidate] = ExtensionFiles::recordedMtime($candidate);
        }

        return new self($candidates, $files, $read);
    }

    /**
     * Whether the walk would list the same files: the same directories to
     * start from, each with the modification time it had.
     *
     * @param list<string> $candidates
     */
    public function isCurrent(array $candidates): bool
    {
        if ($this->candidates !== $candidates) {
            return false;
        }

        foreach ($this->directories as $directory => $mtime) {
            if (ExtensionFiles::mtime($directory) !== $mtime) {
                return false;
            }
        }

        return true;
    }
}
