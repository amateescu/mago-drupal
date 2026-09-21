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

// @mago-expect lint:drupal/author-tag
/**
 * Stands in for a file that credits its writer.
 *
 * @author Someone <someone@example.com>
 */
function author_tag_bad(): void
{
}

// @mago-expect lint:drupal/comment-line-length
// This inline comment is written deliberately long so that it runs past the eighty-character limit.
// @mago-expect lint:drupal/function-comment
function comment_line_length_bad(): void
{
}

// A line with no space in it cannot be wrapped, so it is left alone:
// https://www.drupal.org/docs/develop/standards/php/php-coding-standards#s-line-length-and-wrapping
// @mago-expect lint:drupal/function-comment
function comment_line_length_url(): void
{
}

// @mago-expect lint:drupal/comment-line-length
/**
 * Describes a value.
 *
 * @param string $value
 *   This description of the parameter is written long enough to run past the eighty-character limit.
 */
function comment_line_length_docblock(string $value): void
{
}

/**
 * Shows the lines the length check leaves alone.
 *
 * @param string $value
 *   A short description, followed by an example that keeps its own formatting.
 *
 * @code
 * $result = comment_line_length_exempt('a value that makes this example line run past the limit');
 * @endcode
 *
 * @see https://www.drupal.org/docs/develop/standards/php/php-coding-standards#s-line-length-and-wrapping
 */
function comment_line_length_exempt(string $value): void
{
}

// @mago-expect lint:drupal/comment-line-length
/**
 * Mentions a tag in the middle of a line.
 *
 * @param string $value
 *   A description that names the @see tag mid-line and still runs past the limit.
 */
function comment_line_length_mid_line_tag(string $value): void
{
}

/**
 * Holds a reference comment the length check still measures.
 */
function comment_line_length_indented_reference(): void
{
    // @mago-expect lint:drupal/comment-line-length
    //   @see an indented reference line, which is measured because the exemption
}

/**
 * Holds trailing comments the length check judges on their own text.
 */
function comment_line_length_trailing(): string
{
    // @mago-expect lint:drupal/post-statement-comment
    $reference = 'a'; // @see https://www.drupal.org/project/corpus/issues/3456789012345678
    // @mago-expect lint:drupal/post-statement-comment
    $url = 'b'; // https://www.drupal.org/docs/develop/standards/php/php-coding-standards#s-line

    return $reference . $url;
}
