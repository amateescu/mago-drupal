<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * The extension-owned files one walk of a Drupal root found.
 *
 * @internal
 */
final class ExtensionFileSet
{
    /**
     * @param list<string> $services Every `*.services.yml`, core first.
     * @param list<string> $schemas Every `config/schema/*.schema.yml`, core first.
     * @param array<string, string> $modules Module machine name to its
     *   directory, from every module's `*.info.yml`.
     * @param list<string> $apiFiles Every `*.api.php`, core first.
     * @param list<string> $php Every other PHP file, `.module` and the like
     *   included.
     * @param list<string> $fieldConfigs Every `field.storage.*.yml` and
     *   `field.field.*.yml`.
     * @param array<string, int> $directories Every directory the walk read or
     *   probed, with its modification time, 0 for one that did not exist and
     *   -1 for one that may still be changing. An entry added or removed
     *   changes it; an edited file does not.
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public function __construct(
        public readonly array $services,
        public readonly array $schemas,
        public readonly array $modules,
        public readonly array $apiFiles,
        public readonly array $php,
        public readonly array $fieldConfigs,
        public readonly array $directories,
    ) {}

    /**
     * Whether every directory still has the modification time the walk saw,
     * so its listing is unchanged.
     */
    public function isCurrent(): bool
    {
        foreach ($this->directories as $directory => $mtime) {
            if (ExtensionFiles::mtime($directory) !== $mtime) {
                return false;
            }
        }

        return true;
    }
}
