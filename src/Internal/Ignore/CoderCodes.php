<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Ignore;

/**
 * The phpcs codes of each rule and analyzer check of this extension, for the
 * phpcs comments that name them. `CoderPorts` holds the codes from the Coder
 * map, and this class adds the other names.
 *
 * @internal
 */
final class CoderCodes
{
    /**
     * The other names that phpcs reports the same checks under. Coder 8,
     * which Drupal 11 core runs, has its own sniffs for some of them, and
     * core's `phpcs.xml.dist` adds sniffs that Coder's standards leave out.
     *
     * @var array<string, non-empty-list<string>>
     */
    public const OTHER_NAMES = [
        'drupal/case-semicolon' => ['Drupal.ControlStructures.CaseSemicolon.SemicolonNotAllowed'],
        'drupal/doc-type-namespace' => [
            'Drupal.Classes.UnusedUseStatement.UnusedUse',
            'Drupal.Commenting.DataTypeNamespace.DataTypeNamespace',
        ],
        'drupal/else-if' => ['Drupal.ControlStructures.ElseIf.ElseIfDeclaration'],
        'drupal/method-name-underscore' => ['Drupal.Methods.MethodDeclaration.Underscore'],
        'drupal/non-capturing-catch' => [
            'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch.NonCapturingCatchRequired',
        ],
        'drupal/short-ternary' => [
            'SlevomatCodingStandard.ControlStructures.RequireShortTernaryOperator.RequiredShortTernaryOperator',
        ],
        'drupal/unused-variable' => ['DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable'],
        'drupal/use-leading-backslash' => ['Drupal.Classes.UseLeadingBackslash.SeparatorStart'],
        'drupal/variable-redeclaration' => ['DrupalPractice.CodeAnalysis.VariableAnalysis.VariableRedeclaration'],
    ];

    private function __construct() {}

    /**
     * The phpcs codes of a rule or check, by its `drupal/` code.
     *
     * @return list<string>
     */
    public static function of(string $code): array
    {
        return [...(CoderPorts::PORTS[$code] ?? []), ...(self::OTHER_NAMES[$code] ?? [])];
    }
}
