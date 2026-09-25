<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\TestFiles;
use Mago\Sdk\Analyzer\AfterAnalysisContext;
use Mago\Sdk\Analyzer\AfterAnalysisHook;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\MethodFields;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

use function array_slice;
use function count;
use function in_array;
use function strtolower;

/**
 * Reports plugin managers whose constructor skips `alterInfo()` or
 * `setCacheBackend()`.
 *
 * Ports phpstan-drupal's PluginManagerSetsCacheBackendRule and
 * PluginManagerInspectionRule. What a constructor calls is read off the
 * symbol reference graph once the whole analysis is merged, so the check runs
 * once at the end over the descendants of DefaultPluginManager, the class
 * that has those two methods.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class PluginManagerAuditHook implements AfterAnalysisHook
{
    public const ALTER_CODE = 'plugin-manager-alter-info';

    public const CACHE_CODE = 'plugin-manager-cache-backend';

    private const MANAGER = 'Drupal\Core\Plugin\DefaultPluginManager';

    private const BATCH = 200;

    /**
     * How far a `parent::__construct()` chain is followed.
     */
    private const DEPTH = 5;

    /**
     * Lowercased member names the constructor calls, following
     * `parent::__construct()` so a subclass that leaves the wiring to its
     * parent is not reported.
     *
     * @return list<string>
     */
    private function constructorCalls(AfterAnalysisContext $context, string $class, int $depth = 0): array
    {
        $calls = [];
        $references = $context->analysis->references->getReferencesFrom(new MemberIdentifier($class, '__construct'));
        foreach ($references as $reference) {
            $target = $reference->target;
            if (!$target instanceof MemberIdentifier) {
                continue;
            }

            $member = strtolower($target->member);
            $calls[] = $member;
            if (
                $member === '__construct'
                && $depth < self::DEPTH
                && strtolower($target->class) !== strtolower($class)
            ) {
                $calls = [...$calls, ...$this->constructorCalls($context, $target->class, $depth + 1)];
            }
        }

        return $calls;
    }

    public function afterAnalysis(AfterAnalysisContext $context): void
    {
        $codebase = $context->codebase;
        $names = $codebase->getClassDescendants(self::MANAGER);
        for ($offset = 0, $total = count($names); $offset < $total; $offset += self::BATCH) {
            foreach ($codebase->getMultipleClasses(array_slice($names, $offset, self::BATCH)) as $class) {
                if (
                    $class === null
                    || $class->kind !== ClassLikeKind::Class_
                    || $class->flags->contains(MetadataFlags::ABSTRACT)
                    || strtolower($class->originalName) === strtolower(self::MANAGER)
                    || TestFiles::isTest($class->location->file ?? '')
                    || $context->analysis->getFile($class->location->file ?? '') === null
                ) {
                    // The reference graph covers analyzed files only, so a
                    // manager from an include has no constructor edges to read.
                    continue;
                }

                $constructors = $codebase->findMethods(
                    class: $class->name,
                    name: '__construct',
                    fields: MethodFields::LOCATIONS,
                    declaredOnly: true,
                );
                $constructor = $constructors[0] ?? null;
                $location = $constructor === null ? null : $constructor->nameLocation ?? $constructor->location;
                if ($location === null) {
                    continue;
                }

                $calls = $this->constructorCalls($context, $class->name);
                $name = $class->originalName;
                if (!in_array('alterinfo', $calls, strict: true)) {
                    $context->report(
                        Level::Warning,
                        self::ALTER_CODE,
                        Issue::at(
                            "{$name} never calls alterInfo(), so other modules cannot alter its plugin definitions.",
                            $location,
                        )->withHelp(
                            'Call $this->alterInfo(\'mymodule_data\') in the constructor to invoke hook_mymodule_data_alter().',
                        ),
                    );
                }

                if (!in_array('setcachebackend', $calls, strict: true)) {
                    $context->report(
                        Level::Warning,
                        self::CACHE_CODE,
                        Issue::at(
                            "{$name} never sets a cache backend, so plugin discovery runs on every request.",
                            $location,
                        )->withHelp(
                            'Call $this->setCacheBackend($cache_backend, \'mymodule_plugins\') in the constructor.',
                        ),
                    );
                }
            }
        }
    }
}
