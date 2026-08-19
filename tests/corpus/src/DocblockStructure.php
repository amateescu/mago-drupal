<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/doc-comment
/**
 */
function doc_comment_empty(): void
{
}

// @mago-expect lint:drupal/doc-comment
/**
 * lowercase start.
 */
function doc_comment_bad_capital(): void
{
}

// @mago-expect lint:drupal/doc-comment
/**
 * No terminal punctuation
 */
function doc_comment_no_punctuation(): void
{
}

/**
 * A fine one-line summary.
 */
function doc_comment_fine(): void
{
}

// @mago-expect lint:drupal/doc-comment
/**
 * Spans two
 * physical lines.
 */
function doc_comment_two_line_summary(): void
{
}

// @mago-expect lint:drupal/doc-comment
// @mago-expect lint:drupal/doc-comment
/**
 * @inheritdoc
 */
function doc_comment_bad_inheritdoc(): void
{
}

/**
 * {@inheritdoc}
 */
function doc_comment_fine_inheritdoc(): void
{
}

/**
 * @covers ::something
 */
function doc_comment_fine_covers_only(): void
{
}

// @mago-expect lint:drupal/class-comment
class ClassCommentMissing
{
}

// @mago-expect lint:drupal/class-comment
/*
 * Wrong style comment.
 */
class ClassCommentWrongStyle
{
}

/**
 * Describes what this class actually does.
 */
class ClassCommentFine
{
}

// @mago-expect lint:drupal/class-comment
/**
 * ClassCommentShort.
 */
class ClassCommentShort
{
}

/**
 * Implements hook_node_insert().
 */
function my_module_node_insert($node): void
{
}

/**
 * Implements hook_node_insert() for the page bundle.
 */
function my_module_form_page_form_alter(array &$form): void
{
}

// @mago-expect lint:drupal/hook-comment
// @mago-expect lint:drupal/doc-comment
/**
 * Implements hook_node_insert
 */
function my_module_bad_hook_format($node): void
{
}

// @mago-expect lint:drupal/hook-comment
/**
 * Implements my_module_bad_hook_repeat().
 */
function my_module_bad_hook_repeat($node): void
{
}

// @mago-expect lint:drupal/hook-comment
/**
 * Implements hook_node_insert().
 *
 * @param object $node
 *   The node.
 */
function my_module_hook_dup_param($node): void
{
}

/**
 * Does something that got replaced.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar() instead.
 *
 * @see https://www.drupal.org/node/1234567
 */
function deprecated_tag_fine(): void
{
}

// @mago-expect lint:drupal/deprecated-tag
// @mago-expect lint:drupal/deprecated-tag
/**
 * Does something that got replaced.
 *
 * @deprecated foo bar not matching the grammar at all.
 */
function deprecated_tag_bad_layout(): void
{
}

// @mago-expect lint:drupal/deprecated-tag
/**
 * Does something that got replaced.
 *
 * @deprecated in 10.1.0 and is removed from drupal:11.0.0. Use bar() instead.
 *
 * @see https://www.drupal.org/node/1234567
 */
function deprecated_tag_bad_version(): void
{
}

// @mago-expect lint:drupal/deprecated-tag
/**
 * Does something that got replaced.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0.
 *
 * @see https://www.drupal.org/node/1234567
 */
function deprecated_tag_missing_extra_info(): void
{
}

// @mago-expect lint:drupal/deprecated-tag
/**
 * Does something that got replaced.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar() instead.
 */
function deprecated_tag_missing_see(): void
{
}

// @mago-expect lint:drupal/deprecated-tag
// @mago-expect lint:drupal/function-comment
/**
 * Does something that got replaced.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar() instead.
 *
 * @see https://www.drupal.org/node/1234567.
 */
function deprecated_tag_trailing_period(): void
{
}

// @mago-expect lint:drupal/function-comment
function inline_variable_comment_bad(): void
{
    // @mago-expect lint:drupal/inline-variable-comment
    // @var \Exception $bar
    $bar = new \Exception('x');

    echo $bar->getMessage();
}

// @mago-expect lint:drupal/class-comment
class InlineVariableCommentExempted
{
    // @mago-expect lint:drupal/variable-comment
    // @var \Exception
    protected \Exception $exempted;

    // @mago-expect lint:drupal/function-comment
    public function read(): \Exception
    {
        return $this->exempted;
    }
}

// @mago-expect lint:drupal/inline-variable-comment
// @mago-expect lint:drupal/doc-comment
/**
 * @var $bar \Exception Wrong word order.
 */
function inline_variable_comment_bad_order(): void
{
}

// @mago-expect lint:drupal/doc-comment
/**
 * @var \Exception Fine order.
 */
function inline_variable_comment_fine_order(): void
{
}

// @mago-expect lint:drupal/function-comment
function inline_comment_examples(): void
{
    // @mago-expect lint:drupal/inline-comment
    # Hash style comment.
    $a = 1;

    // @mago-expect lint:drupal/inline-comment
    // lowercase start.
    $b = 2;

    // @mago-expect lint:drupal/inline-comment
    // No terminal punctuation
    $c = 3;

    // Fine comment.
    $d = 4;

    // corpus_machine_name is a machine name.
    $e = 5;

    echo $a . $b . $c . $d . $e;
}

// @mago-expect lint:drupal/class-comment
class VariableCommentFixture
{
    // @mago-expect lint:drupal/variable-comment
    protected $missing;

    protected string $fineNativeType;

    // @mago-expect lint:drupal/variable-comment
    /*
     * Wrong style.
     */
    protected $wrongStyle;

    /**
     * A fine property.
     *
     * @var string
     */
    protected $fine;

    // @mago-expect lint:drupal/variable-comment
    /**
     * No @var tag here, and no native type either.
     */
    protected $missingVar;

    // @mago-expect lint:drupal/variable-comment
    // @mago-expect lint:drupal/doc-comment
    /**
     * @var string
     * @var int
     */
    protected $duplicateVar;

    // @mago-expect lint:drupal/variable-comment
    // @mago-expect lint:drupal/doc-comment
    /**
     * @var string $inlineRepeat Should not repeat the name.
     */
    protected $inlineRepeat;
}

/**
 * Implements hook_node_insert().
 */
// @mago-expect lint:drupal/preg-security
function my_module_directive_between_docblock_and_function($node): void
{
    preg_match('/(.*)/e', 'unused');
}

// @mago-expect lint:drupal/function-comment
function inline_comment_wraps_across_two_lines(): void
{
    // A comment that wraps across two physical lines reads as one sentence,
    // so neither line is judged as if it were the whole comment on its own.
    $result = 1;

    echo $result;
}

// @mago-expect lint:drupal/function-comment
function doc_comment_ignores_a_local_var_annotation(array $data): void
{
    /** @var \Exception $error */
    $error = $data['error'];

    echo $error->getMessage();
}

/**
 * A @code example may precede the first real tag without being "not first".
 *
 * @code
 * $example = 'demonstration only';
 * @endcode
 *
 * @param string $value
 *   The value.
 */
function doc_comment_exempts_code_from_param_order($value): void
{
}

/**
 * Documents a structure list.
 *
 * The colon below introduces a list, so it is a fine long-description ending:
 */
function doc_comment_long_description_may_end_with_a_colon(): void
{
}

// @mago-expect lint:drupal/doc-comment
/**
 * Fine summary.
 *
 * This long description just trails off
 */
function doc_comment_long_description_must_not_end_with_a_letter(): void
{
}

// @mago-expect lint:drupal/doc-comment
/**
 * Documents two parameters split apart by an example.
 *
 * @param string $first
 *   The first parameter.
 *
 * @code
 * $example = corpus_split_param_groups('a', 'b');
 * @endcode
 *
 * @param string $second
 *   The second parameter.
 */
function corpus_split_param_groups(string $first, string $second): string
{
    return $first . $second;
}

/**
 * @defgroup corpus_group Corpus group
 * @{
 * Groups the corpus fixtures, exempt as a whole like any api.module topic
 *
 * @section corpus_section A section heading
 */

/**
 * Belongs to the documentation group opened above.
 */
function doc_comment_inside_a_documentation_group(): void
{
}

// @mago-expect lint:drupal/function-comment
function inline_comment_directive_shapes(): void
{
    // cspell:ignore corpusword otherword
    $a = 1;

    // @mago-expect lint:drupal/inline-comment
    // This sentence is cut short by the reference below
    // @see https://example.com/reference
    $b = 2;

    // A trailing directive has to share the statement's line to work.
    $c = 1; // phpcs:ignore Drupal.Some.Sniff

    echo $a . $b . $c;
}

/**
 * @}
 */
