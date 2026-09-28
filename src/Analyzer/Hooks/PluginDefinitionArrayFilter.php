<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\NamedFunctions;
use amateescu\MagoDrupal\Internal\PluginDefinitions;
use amateescu\MagoDrupal\Internal\TraitUsers;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;

use function in_array;
use function preg_match;
use function str_contains;
use function substr;

/**
 * Drops `invalid-array-access` on a plugin definition read as the array it is.
 *
 * `PluginBase` documents `$pluginDefinition` and `getPluginDefinition()` as
 * `array|PluginDefinitionInterface`, and `DeriverInterface` documents the base
 * definition the same way, so Mago reports every `['key']` on one for the
 * object half. On a plugin's own definition, the plugin types with a
 * definition class keep the report; `PluginDefinitions` says which ones, and
 * in a trait every class using it has to pass. In a deriver every report
 * naming that union goes: a deriver for a plugin type with definition objects
 * calls their methods instead.
 *
 * @internal
 */
final class PluginDefinitionArrayFilter implements IssueFilterHook
{
    /**
     * The object half Mago names for `array|PluginDefinitionInterface`.
     */
    private const OBJECT_HALF = '`Drupal\Component\Plugin\Definition\PluginDefinitionInterface`';

    /**
     * The plugin's own definition, read through the property or the getter.
     */
    private const OWN_DEFINITION = '/^\$this\s*->\s*(?:pluginDefinition|getPluginDefinition\s*\(\s*\))\s*\[/';

    private const DERIVER = 'drupal\component\plugin\derivative\deriverinterface';

    private string $contents = '';

    private ?NamedFunctions $functions = null;

    public function getCodes(): array
    {
        return ['invalid-array-access'];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $issue = $context->issue;
        if ($issue->annotations === [] || !str_contains($issue->message, self::OBJECT_HALF)) {
            return IssueFilterDecision::Keep;
        }

        // The first annotation is the one the issue points at.
        $span = $issue->annotations[0]->span;

        $owner = $this->functions($context->contents)->at($span->start)[2] ?? null;
        $class = $owner === null ? null : $context->codebase->getClassLike($owner);
        if ($class === null) {
            return IssueFilterDecision::Keep;
        }

        // In a deriver every such value is a definition, and one serving a
        // plugin type with definition objects works with them through methods.
        if (in_array(self::DERIVER, $class->parentInterfaces, strict: true)) {
            return IssueFilterDecision::Remove;
        }

        $text = substr($context->contents, $span->start, $span->end - $span->start);

        return preg_match(self::OWN_DEFINITION, $text) === 1 && self::areArrays($context->codebase, $class)
            ? IssueFilterDecision::Remove
            : IssueFilterDecision::Keep;
    }

    /**
     * Whether the class has array definitions, or for a trait, whether every
     * class using it does.
     */
    private static function areArrays(Codebase $codebase, ClassLikeMetadata $class): bool
    {
        if ($class->kind !== ClassLikeKind::Trait) {
            return PluginDefinitions::areArrays($codebase, $class);
        }

        $users = TraitUsers::classes($codebase, $class->name);
        foreach ($users === [] ? [] : $codebase->getMultipleClassLikes($users) as $user) {
            if ($user === null || !PluginDefinitions::areArrays($codebase, $user)) {
                return false;
            }
        }

        return $users !== [];
    }

    /**
     * The named functions of the file the issue is in, tokenized once for
     * the issues of that file.
     */
    private function functions(string $contents): NamedFunctions
    {
        if ($this->functions === null || $this->contents !== $contents) {
            $this->functions = NamedFunctions::of($contents);
            $this->contents = $contents;
        }

        return $this->functions;
    }
}
