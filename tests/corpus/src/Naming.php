<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/redundant-use
use Exception;
// The leading backslash spells the same global import.
// @mago-expect lint:drupal/redundant-use
use \RuntimeException;
use Drupal\corpus\Nested\Thing;
use Drupal\corpus\Nested\Thing as AliasedThing;

use function preg_match;

/**
 * Exercises the naming and import rules ported as native linter rules.
 */
class Naming
{
    public string $goodName = '';

    // @mago-expect lint:drupal/property-name
    public string $bad_name = '';

    // @mago-expect lint:drupal/property-name
    public string $BadName = '';

    protected ?Thing $thing = null;

    protected ?AliasedThing $aliased = null;

    // @mago-expect lint:drupal/function-comment
    public function matches(string $input): bool
    {
        return preg_match('/^[a-z]+$/', $input) === 1;
    }

    // @mago-expect lint:drupal/function-comment
    public function fail(): never
    {
        throw new Exception('boom');
    }

    // @mago-expect lint:drupal/function-comment
    public function failHarder(): never
    {
        throw new RuntimeException('boom');
    }
}

/**
 * Exercises drupal/enum-case-name.
 */
enum Status
{
    case Enabled;

    // @mago-expect lint:drupal/enum-case-name
    case not_enabled;

    // @mago-expect lint:drupal/enum-case-name
    case Half_Enabled;

    // The attribute's identifier comes first in the subtree.
    // This pins the rule to the case name rather than the attribute name.
    // @mago-expect lint:drupal/enum-case-name
    #[CorpusAttribute] case attributed_bad_name;

    #[CorpusAttribute] case AttributedFine;
}
