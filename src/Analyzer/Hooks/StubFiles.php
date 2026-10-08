<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use Closure;
use Mago\Sdk\Analyzer\InitializationContext;
use Mago\Sdk\Analyzer\InitializationHook;

use function basename;
use function dirname;
use function file_get_contents;
use function glob;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function sort;

/**
 * Loads the shipped stub files into the analyzer's symbol table.
 *
 * The stubs sharpen core signatures Drupal documents loosely. Mago reads them
 * for symbols only: they are never linted, formatted or reported on, and a
 * member the stub does not name keeps the declaration core ships.
 *
 * @internal
 */
final class StubFiles implements InitializationHook
{
    /**
     * A member whose docblock names the core release that removes it, with
     * the docblock and the declaration line.
     */
    private const REMOVED_MEMBER = '~\n[ \t]*/\*\*(?:(?!\*/).)*?@stub-removed-in (\d+)\.(\d+)(?:(?!\*/).)*?\*/[ \t]*\n[^\n]*;\n~s';

    /**
     * A docblock naming the core release that deprecates its member.
     */
    private const DEPRECATED_DOCBLOCK = '~/\*\*(?:(?!\*/).)*?@stub-deprecated-in (\d+)\.(\d+)(?:(?!\*/).)*?\*/~s';

    /**
     * The `@deprecated` tag and its continuation lines, up to the next tag.
     */
    private const DEPRECATED_TAG = '~[ \t]*\*[ \t]*@deprecated\b.*?(?=[ \t]*\*[ \t]*@|[ \t]*\*/)~s';

    private readonly string $directory;

    /**
     * @param Closure(): ?string $coreVersion The installed core version, or
     *   null when there is no core to read it from.
     */
    public function __construct(
        ?string $directory = null,
        private readonly ?Closure $coreVersion = null,
    ) {
        $this->directory = $directory ?? dirname(__DIR__, levels: 3) . '/resources/stubs';
    }

    public function initialize(InitializationContext $context): void
    {
        $version = $this->coreVersion === null ? null : ($this->coreVersion)();
        foreach ($this->files() as $file) {
            $bytes = file_get_contents($file);
            if ($bytes === false) {
                continue;
            }

            $context->addStub(basename($file), self::forVersion($bytes, $version));
        }
    }

    /**
     * The stub as the core version has it: without the members a release up
     * to the version removes, so a call to one is reported as missing, and
     * without the `@deprecated` of a member the version does not deprecate
     * yet.
     */
    public static function forVersion(string $stub, ?string $version): string
    {
        $matches = [];
        if ($version === null || preg_match('/^(\d+)\.(\d+)/', $version, $matches) !== 1) {
            return $stub;
        }

        $release = ((int) $matches[1] * 1000) + (int) $matches[2];
        $stub = (string) preg_replace_callback(
            self::REMOVED_MEMBER,
            static fn(array $member): string => $release >= (((int) $member[1] * 1000) + (int) $member[2])
                ? "\n"
                : $member[0],
            $stub,
        );

        return (string) preg_replace_callback(
            self::DEPRECATED_DOCBLOCK,
            static fn(array $docblock): string => $release < (((int) $docblock[1] * 1000) + (int) $docblock[2])
                ? (string) preg_replace(self::DEPRECATED_TAG, replacement: '', subject: $docblock[0])
                : $docblock[0],
            $stub,
        );
    }

    /**
     * The stub files, in a stable order so every worker sees the same set.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $files = glob($this->directory . '/*.stub');
        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }
}
