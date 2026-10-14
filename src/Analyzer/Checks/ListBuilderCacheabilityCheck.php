<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use Closure;
use Mago\Sdk\Analyzer\Codebase;

use function preg_match;

/**
 * Reports list builder operation methods without the CacheableMetadata
 * parameter Drupal 11.3 added; runs on EntityListBuilderInterface descendants.
 *
 * Ports phpstan-drupal's EntityListBuilderOperationsCacheabilityRule. Core
 * 11.3 and 11.4 keep the parameter commented out in the interface and read it
 * through `func_get_args()`, so the check applies on that version window, read
 * off `Drupal::VERSION`, and nothing is reported without a core checkout.
 *
 * @internal
 */
final class ListBuilderCacheabilityCheck implements MetadataCheck
{
    public const CODE = 'list-builder-cacheability';

    public const ANCESTORS = ['Drupal\Core\Entity\EntityListBuilderInterface'];

    private const BASE = 'Drupal\Core\Entity\EntityListBuilder';

    /**
     * @param Closure(Codebase): ?string $coreVersion
     */
    public function __construct(
        private readonly Closure $coreVersion,
    ) {}

    public function textGate(): ?string
    {
        return null;
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        $name = $class->name();
        if ($name === self::BASE || !self::commentedOut(($this->coreVersion)($class->codebase))) {
            return;
        }

        foreach (['getOperations', 'getDefaultOperations'] as $operation) {
            $method = $class->method($operation);
            if (
                $method === null
                || $method->location === null
                || HookMethods::typed(
                    HookMethods::parameters($method)[1] ?? null,
                    EntityOperationCacheabilityCheck::CACHEABLE_METADATA,
                )
            ) {
                continue;
            }

            $reporter->error(self::CODE, Reporter::issue(
                "{$name}::{$operation}() is missing the CacheableMetadata parameter added in Drupal 11.3.",
                $method->nameLocation ?? $method->location,
                "Change the signature to {$operation}(EntityInterface \$entity, ?CacheableMetadata \$cacheability = NULL).",
                'https://www.drupal.org/node/3533080',
            ));
        }
    }

    /**
     * Whether core at this version keeps the parameter commented out and
     * reads it through `func_get_args()`.
     */
    public static function commentedOut(?string $version): bool
    {
        // Major and minor only: `11.3-dev` is 11.3 for this, which
        // version_compare() puts below `11.3`.
        $matches = [];
        if ($version === null || preg_match('/^(\d+)\.(\d+)/', $version, $matches) !== 1) {
            return false;
        }

        $release = ((int) $matches[1] * 1000) + (int) $matches[2];

        return $release >= 11_003 && $release < 12_000;
    }
}
