<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function array_key_exists;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Reports a method name that starts with one underscore, or with two when the
 * method is not a PHP magic method.
 *
 * Ports PSR2.Methods.MethodDeclaration.Underscore and the MethodDoubleUnderscore
 * code of Drupal.NamingConventions.ValidFunctionName, which Coder 9's Drupal
 * standard enables. An underscore once marked a method as private; the
 * visibility keyword does that now. Two underscores start a magic method
 * and are fine.
 */
final class MethodNameUnderscoreRule implements Rule
{
    /**
     * The names, lower case and without the two underscores, that Coder 9
     * accepts after a double underscore. The first 17 are PHP's magic methods.
     * The rest are the methods of PHP's SoapClient.
     */
    private const DOUBLE_UNDERSCORE_NAMES = [
        'construct' => true,
        'destruct' => true,
        'call' => true,
        'callstatic' => true,
        'get' => true,
        'set' => true,
        'isset' => true,
        'unset' => true,
        'sleep' => true,
        'wakeup' => true,
        'serialize' => true,
        'unserialize' => true,
        'tostring' => true,
        'invoke' => true,
        'set_state' => true,
        'clone' => true,
        'debuginfo' => true,
        'dorequest' => true,
        'getcookies' => true,
        'getfunctions' => true,
        'getlastrequest' => true,
        'getlastrequestheaders' => true,
        'getlastresponse' => true,
        'getlastresponseheaders' => true,
        'gettypes' => true,
        'setcookie' => true,
        'setlocation' => true,
        'setsoapheaders' => true,
        'soapcall' => true,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/method-name-underscore',
            name: 'Method name underscore',
            description: 'Reports method names that start with an underscore, other than PHP magic methods.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $identifier = Nodes::declaredIdentifier($context->file, $context->node);
        if ($identifier === null) {
            return;
        }

        $name = $context->file->getText($identifier);
        if (!str_starts_with($name, '_')) {
            return;
        }

        if (!str_starts_with($name, '__')) {
            $context->report(Issue::new(
                "Do not start the method name {$name}() with an underscore to mark its visibility.",
                $identifier->span,
            ));

            return;
        }

        // Exactly two underscores start the name. A bare `__` and a name with
        // three underscores are not this check.
        $rest = substr($name, offset: 2);
        if ($rest === '' || $rest[0] === '_' || array_key_exists(strtolower($rest), self::DOUBLE_UNDERSCORE_NAMES)) {
            return;
        }

        $context->report(Issue::new(
            "The method name {$name}() starts with a double underscore, but it is not a PHP magic method.",
            $identifier->span,
        ));
    }
}
