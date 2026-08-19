<?php

declare(strict_types=1);

namespace Drupal\corpus;

use function trigger_error;

// A deprecated-file notice sits at file scope, outside any declaration.
// This one is missing its change record, so the rule must still fire here.
// @mago-expect lint:drupal/deprecation-message
trigger_error(
    'DeprecationMessage.php is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar().',
    E_USER_DEPRECATED,
);

/**
 * Stands in for a real function with a strict deprecation notice.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar().
 *
 * @see https://www.drupal.org/node/1234567
 */
function tagged_and_correct(): void
{
    trigger_error(
        'foo() is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar(). See https://www.drupal.org/node/1234567',
        E_USER_DEPRECATED,
    );
}

// @mago-expect lint:drupal/function-comment
function relaxed_and_correct(): void
{
    trigger_error(
        'foo() is deprecated in drupal:10.1.0 and will be removed before drupal:11.0.0. See https://www.drupal.org/node/1234567',
        E_USER_DEPRECATED,
    );
}

// @mago-expect lint:drupal/function-comment
function missing_the_change_record(): void
{
    // @mago-expect lint:drupal/deprecation-message
    trigger_error(
        'foo() is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar().',
        E_USER_DEPRECATED,
    );
}

// @mago-expect lint:drupal/function-comment
function unversioned(): void
{
    // Both versions are reported, and one pragma absorbs one issue.
    // @mago-expect lint:drupal/deprecation-message
    // @mago-expect lint:drupal/deprecation-message
    trigger_error(
        'foo() is deprecated in 10.1.0 and is removed from 11.0.0. Use bar(). See https://www.drupal.org/node/1234567',
        E_USER_DEPRECATED,
    );
}

// @mago-expect lint:drupal/function-comment
function link_with_trailing_period(): void
{
    // @mago-expect lint:drupal/deprecation-message
    trigger_error(
        'foo() is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar(). See https://www.drupal.org/node/1234567.',
        E_USER_DEPRECATED,
    );
}

// @mago-expect lint:drupal/function-comment
function fully_qualified_level(): void
{
    // @mago-expect lint:drupal/deprecation-message
    trigger_error(
        'foo() is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar().',
        \E_USER_DEPRECATED,
    );
}

// @mago-expect lint:drupal/function-comment
function not_a_deprecation(): void
{
    trigger_error('something went wrong', E_USER_WARNING);
}
