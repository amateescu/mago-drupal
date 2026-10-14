<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\Arguments;
use amateescu\MagoDrupal\Internal\EntityTypeIndex;
use amateescu\MagoDrupal\Internal\Expressions;
use amateescu\MagoDrupal\Internal\TestFiles;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function preg_quote;
use function substr;

/**
 * Reports entity type manager lookups of an id no entity type declares.
 *
 * `getDefinition($id, FALSE)` asks for null instead of an exception, so it is
 * a probe and is left alone, and so is a lookup after a `hasDefinition()`
 * call on the same literal id in the same function: code that talks to an
 * optional module checks that its entity type exists, around the lookup or
 * with an early return. Test code is skipped, since tests mock the
 * manager. The plugin registers one hook for the getters that take the id
 * alone and one for those with a second parameter, since only the second
 * kind needs the call's syntax.
 *
 * @internal
 */
final class UnknownEntityTypeHook implements MethodCallAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is reported
     * as `drupal/unknown-entity-type`.
     */
    public const CODE = 'unknown-entity-type';

    /**
     * The one entity type every Drupal site has, since the user module cannot
     * be uninstalled. Without it in the index, core is outside the analyzed
     * code and an unknown id proves nothing.
     */
    public const SENTINEL = 'user';

    private const MANAGER = 'Drupal\Core\Entity\EntityTypeManagerInterface';

    /**
     * Getters that take the entity type id and nothing else, so the id is the
     * first argument type however the call names it.
     */
    public const SINGLE = ['getStorage', 'getAccessControlHandler', 'getViewBuilder', 'getListBuilder'];

    /**
     * Getters with a second parameter. A call naming its arguments can pass
     * the second one first, so matching them needs the call's syntax.
     */
    public const PAIRED = ['getFormObject', 'getHandler', 'getDefinition'];

    /**
     * @param Closure(Codebase): EntityTypeIndex $index
     * @param non-empty-list<string> $getters SINGLE or PAIRED.
     */
    public function __construct(
        private readonly Closure $index,
        private readonly array $getters,
    ) {}

    public function getTargets(): array
    {
        $targets = [];
        foreach ($this->getters as $method) {
            $targets[] = MethodTarget::exact(self::MANAGER, $method);
        }

        return $targets;
    }

    public function getRequirements(): array
    {
        return (
            $this->getters === self::SINGLE
                ? [FileAnalysisRequirement::ArgumentTypes]
                : [
                    FileAnalysisRequirement::ArgumentTypes,
                    FileAnalysisRequirement::TargetSubtree,
                    FileAnalysisRequirement::SourceText,
                ]
        );
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if (TestFiles::isTestOrHookDocumentation($context->analysis->file)) {
            return;
        }

        $single = $this->getters === self::SINGLE;
        $id = ($single
            ? $context->argumentTypes[0] ?? null
            : Arguments::type($context, position: 0, names: 'entity_type_id'))?->getLiteralString();
        if ($id === null || $id === '') {
            return;
        }

        // Only getDefinition() takes a bool second argument, and a literal
        // FALSE there asks for null instead of an exception.
        $second = $single ? null : Arguments::type($context, position: 1, names: 'exception_on_invalid');
        if ($second?->getLiteralBool() === false) {
            return;
        }

        $index = ($this->index)($context->codebase);
        if ($index->get(self::SENTINEL) === null || $index->declares($id) || self::guarded($context, $id)) {
            return;
        }

        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new(
                "No entity type with id \"{$id}\" is defined.",
                $context->node->span,
                'unknown entity type id',
            )->withHelp(
                'Check the id against the entity classes, or make sure the module providing it is part of the analyzed code.',
            ),
        );
    }

    /**
     * Whether the code before the call, in the function that holds it, calls
     * `hasDefinition()` with the id as a literal.
     */
    private static function guarded(NodeAnalysisContext $context, string $id): bool
    {
        $file = $context->analysis->getSourceFile();
        $call = Expressions::at($file, NodeKind::MethodCall, $context->node->span);
        if ($call === null) {
            return false;
        }

        $scope = Expressions::scopeOf($file, $call);
        $before = substr($file->contents, $scope->span->start, $call->span->start - $scope->span->start);

        return preg_match('/\bhasDefinition\(\s*([\'"])' . preg_quote($id, delimiter: '/') . '\1\s*\)/', $before) === 1;
    }
}
