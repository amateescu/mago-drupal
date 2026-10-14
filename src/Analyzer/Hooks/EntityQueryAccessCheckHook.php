<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Providers\EntityQueries;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

/**
 * Reports an entity query executed without `accessCheck()`.
 *
 * Ports phpstan-drupal's EntityQueryHasAccessCheckRule. Since Drupal 10 an
 * entity query throws unless access checking was set explicitly. Config
 * entity queries have no access checking and are left alone. The receiver's
 * tags say what happened on the chain and what kind of entity type it is, so
 * the hook reads nothing else; a query of unknown kind is reported, since
 * only a known config entity type is exempt.
 *
 * @internal
 */
final class EntityQueryAccessCheckHook implements MethodCallAnalysisHook
{
    public const CODE = 'entity-query-access-check';

    public function getTargets(): array
    {
        return [MethodTarget::exact(EntityQueries::QUERY, 'execute')];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::ReceiverType];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        // The receiver may be a union; one unchecked branch is enough.
        foreach (EntityQueries::allTags($context->receiverType) as [$_, $entityType, $kind, $access, $_]) {
            if ($access !== EntityQueries::UNCHECKED || $kind === EntityQueries::CONFIG) {
                continue;
            }

            $subject = $entityType === EntityQueries::UNKNOWN ? 'This' : "This {$entityType}";
            $context->report(
                Level::Error,
                self::CODE,
                Issue::new(
                    "{$subject} entity query runs without accessCheck(), which throws since Drupal 10.",
                    $context->node->span,
                    'no accessCheck() on the chain',
                )->withHelp(
                    'Call ->accessCheck(TRUE) to respect entity access, or ->accessCheck(FALSE) when the query deliberately bypasses it.',
                )->withLink('https://www.drupal.org/node/3201242'),
            );

            return;
        }
    }
}
