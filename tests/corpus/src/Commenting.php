<?php

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\corpus\Nested\Thing;

// @mago-expect lint:drupal/gender-neutral-comment
// This checks his configuration before returning.
// @mago-expect lint:drupal/function-comment
function gender_neutral_bad(): void
{
}

// This checks the configuration before returning.
// @mago-expect lint:drupal/function-comment
function gender_neutral_fine(): void
{
}

// @mago-expect lint:drupal/function-comment
function post_statement_bad(): void
{
    // @mago-expect lint:drupal/post-statement-comment
    $result = 1; // A trailing comment.

    echo $result;
}

// @mago-expect lint:drupal/function-comment
function post_statement_fine(): void
{
    // A comment on its own line.
    $result = 1;

    foreach ([1, 2] as $item) {
        $result += $item;
    } // A comment right after a closing brace is allowed.

    echo $result;
}

// @mago-expect lint:drupal/todo-comment
// TODO: fix this properly.
// @mago-expect lint:drupal/function-comment
function todo_bad(): void
{
}

// @todo Fix this properly.
// @mago-expect lint:drupal/function-comment
function todo_fine(): void
{
}

// @mago-expect lint:drupal/doc-comment-array-syntax
/**
 * Demonstrates array syntax inside a @code example.
 *
 * @code
 * $foo = array(1, 2, 3);
 * @endcode
 */
function doc_comment_array_syntax_bad(): void
{
}

/**
 * Demonstrates array syntax inside a @code example.
 *
 * @code
 * $foo = [1, 2, 3];
 * @endcode
 */
function doc_comment_array_syntax_fine(): void
{
}

/**
 * Exercises the legacy sniffs Drupal.Commenting.* still ports.
 */
class CommentingLegacyTest
{
    // @mago-expect lint:drupal/expected-exception-tag
    /**
     * Stands in for a PHPUnit test method.
     *
     * @expectedException \Exception
     */
    public function expectedExceptionBad(): void
    {
    }

    // @mago-expect lint:drupal/function-comment
    public function expectedExceptionFine(): void
    {
    }

    // @mago-expect lint:drupal/doc-type-namespace
    /**
     * Reads a value keyed by its short, unqualified name.
     *
     * @param Thing $thing
     *   The thing to key by.
     */
    public function docTypeNamespaceBad($thing): void
    {
    }

    /**
     * Reads a value keyed by its fully qualified name.
     *
     * @param \Drupal\corpus\Nested\Thing $thing
     *   The thing to key by.
     */
    public function docTypeNamespaceFine($thing): void
    {
    }
}
