<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/doc-comment
/**
 */
function doc_comment_empty(): void {
}

// @mago-expect lint:drupal/doc-comment
/**
 * lowercase start.
 */
function doc_comment_bad_capital(): void {
}

// @mago-expect lint:drupal/doc-comment
/**
 * No terminal punctuation
 */
function doc_comment_no_punctuation(): void {
}

/**
 * A fine one-line summary.
 */
function doc_comment_fine(): void {
}

// Coder wants an upper-case letter first, and a `#` is not one.
// @mago-expect lint:drupal/doc-comment
/**
 * #123: Starts with an issue number.
 */
function doc_comment_hash_start(): void {
}

/**
 * élan is fine, since Coder does not test a multi-byte first letter.
 */
function doc_comment_multibyte_start(): void {
}

// @mago-expect lint:drupal/doc-comment
/**
 * Spans two
 * physical lines.
 */
function doc_comment_two_line_summary(): void {
}

// The braces report stands in for the missing summary, as in Coder.
// @mago-expect lint:drupal/doc-comment
/**
 * @inheritdoc
 */
function doc_comment_bad_inheritdoc(): void {
}

/**
 * {@inheritdoc}
 */
function doc_comment_fine_inheritdoc(): void {
}

/**
 * Has a summary, so Coder does not read the bare tag below.
 *
 * @inheritdoc
 */
function doc_comment_inheritdoc_after_summary(): void {
}

/**
 * @covers ::something
 */
function doc_comment_fine_covers_only(): void {
}

/**
 * @covers ::something
 * @group corpus
 */
function doc_comment_fine_covers_first(): void {
}

// Coder reads only the first tag, and `@group` there needs a summary.
// @mago-expect lint:drupal/doc-comment
/**
 * @group corpus
 * @covers ::something
 */
function doc_comment_covers_not_first(): void {
}

// @mago-expect lint:drupal/class-comment
class ClassCommentMissing {}

// @mago-expect lint:drupal/class-comment
/*
 * Wrong style comment.
 */
class ClassCommentWrongStyle {}

/**
 * Describes what this class actually does.
 */
class ClassCommentFine {}

// @mago-expect lint:drupal/class-comment
/**
 * ClassCommentShort.
 */
class ClassCommentShort {}

/**
 * Implements hook_node_insert().
 */
function my_module_node_insert($node): void {
}

/**
 * Implements hook_node_insert() for the page bundle.
 */
function my_module_form_page_form_alter(array &$form): void {
}

// @mago-expect lint:drupal/hook-comment
// @mago-expect lint:drupal/doc-comment
/**
 * Implements hook_node_insert
 */
function my_module_bad_hook_format($node): void {
}

// @mago-expect lint:drupal/hook-comment
/**
 * Implements my_module_bad_hook_repeat().
 */
function my_module_bad_hook_repeat($node): void {
}

// @mago-expect lint:drupal/hook-comment
/**
 * Implements hook_node_insert().
 *
 * @param object $node
 *   The node.
 */
function my_module_hook_dup_param($node): void {
}

/**
 * Does something that got replaced.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar() instead.
 *
 * @see https://www.drupal.org/node/1234567
 */
function deprecated_tag_fine(): void {
}

// @mago-expect lint:drupal/deprecated-tag
// @mago-expect lint:drupal/deprecated-tag
/**
 * Does something that got replaced.
 *
 * @deprecated foo bar not matching the grammar at all.
 */
function deprecated_tag_bad_layout(): void {
}

// @mago-expect lint:drupal/deprecated-tag
/**
 * Does something that got replaced.
 *
 * @deprecated in 10.1.0 and is removed from drupal:11.0.0. Use bar() instead.
 *
 * @see https://www.drupal.org/node/1234567
 */
function deprecated_tag_bad_version(): void {
}

// @mago-expect lint:drupal/deprecated-tag
/**
 * Does something that got replaced.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0.
 *
 * @see https://www.drupal.org/node/1234567
 */
function deprecated_tag_missing_extra_info(): void {
}

// @mago-expect lint:drupal/deprecated-tag
/**
 * Does something that got replaced.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar() instead.
 */
function deprecated_tag_missing_see(): void {
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
function deprecated_tag_trailing_period(): void {
}

// @mago-expect lint:drupal/function-comment
function inline_variable_comment_bad(): void {
  // @mago-expect lint:drupal/inline-variable-comment
  // @var \Exception $bar
  $bar = new \Exception('x');

  // @mago-expect lint:drupal/inline-variable-comment
  /** @var $bar \Exception Wrong word order. */
  echo $bar->getMessage();
}

// @mago-expect lint:drupal/class-comment
class InlineVariableCommentExempted {

  // @mago-expect lint:drupal/variable-comment
  // @var \Exception
  protected \Exception $exempted;

  // Coder looks past a comment between the @var line and the declaration.
  // @mago-expect lint:drupal/variable-comment
  // @var \Exception $between
  // Explains the property.
  protected \Exception $between;

  // @mago-expect lint:drupal/variable-comment
  /**
   * Coder's inline check skips the docblock of a declaration.
   *
   * @var $order \Exception
   */
  protected $order;

  // @mago-expect lint:drupal/function-comment
  public function read(): \Exception {
    return $this->exempted;
  }

}

// Coder does not check the word order in the docblock of a declaration.
// @mago-expect lint:drupal/doc-comment
/**
 * @var $bar \Exception Wrong word order.
 */
function inline_variable_comment_bad_order(): void {
}

// @mago-expect lint:drupal/doc-comment
/**
 * @var \Exception Fine order.
 */
function inline_variable_comment_fine_order(): void {
}

// @mago-expect lint:drupal/function-comment
function inline_comment_examples(): void {
  // @mago-format-ignore-start
  // @mago-expect lint:drupal/inline-comment
  // The formatter would turn the next comment into a `//` one.
  # Hash style comment.
  // @mago-format-ignore-end
  $a = 1;

  // @mago-expect lint:drupal/inline-comment
  // lowercase start.
  $b = 2;

  // @mago-expect lint:drupal/inline-comment-punctuation
  // No terminal punctuation
  $c = 3;

  // Fine comment.
  $d = 4;

  // corpus_machine_name is a machine name.
  $e = 5;

  echo $a . $b . $c . $d . $e;
}

// @mago-expect lint:drupal/function-comment
function inline_comment_after_brace(bool $flag): bool {
  // A comment after a closing brace is skipped, as Coder skips it.
  if ($flag) {
    $flag = FALSE;
  } // end of the if
  return $flag;
}

// @mago-expect lint:drupal/function-comment
function inline_comment_run_after_brace(int ...$values): array {
  // The lines below the end-of-block comment are a comment of their own.
  // @mago-expect lint:drupal/inline-comment
  // @mago-expect lint:drupal/inline-comment-punctuation
  return array_map(
    static function (int $value): int {
      if ($value > 10) {
        $value = 10;
      } // End of the if
      // lowercase start below the brace
      return $value;
    },
    $values,
  );
}

// @mago-expect lint:drupal/class-comment
class VariableCommentFixture {

  // @mago-expect lint:drupal/variable-comment
  protected $missing;

  // @mago-expect lint:drupal/variable-comment
  protected string $missingNativeType;

  /**
   * A native type makes the @var tag optional.
   */
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

  // @mago-expect lint:drupal/variable-comment
  /**
   * Coder reports a variable name before the type.
   *
   * @var $nameFirst string
   */
  protected $nameFirst;

  /**
   * Coder takes $this as a type.
   *
   * @var $this|null
   */
  protected $parent;

}

/**
 * Implements hook_node_insert().
 */
// @mago-ignore lint:drupal/preg-security
function my_module_directive_between_docblock_and_function($node): void {
  preg_match('/(.*)/e', 'unused');
}

// @mago-expect lint:drupal/function-comment
function inline_comment_wraps_across_two_lines(): void {
  // A comment that wraps across two physical lines is one sentence,
  // so neither line is judged as if it were the whole comment on its own.
  $result = 1;

  echo $result;
}

// @mago-expect lint:drupal/function-comment
function doc_comment_ignores_a_local_var_annotation(array $data): void {
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
function doc_comment_exempts_code_from_param_order($value): void {
}

/**
 * Documents a structure list.
 *
 * The colon below introduces a list, so it is a fine long-description ending:
 */
function doc_comment_long_description_may_end_with_a_colon(): void {
}

// @mago-expect lint:drupal/long-description-punctuation
/**
 * Fine summary.
 *
 * This long description just trails off
 */
function doc_comment_long_description_must_not_end_with_a_letter(): void {
}

// @mago-expect lint:drupal/doc-comment
// @mago-expect lint:drupal/function-comment
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
function corpus_split_param_groups(string $first, string $second): string {
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
function doc_comment_inside_a_documentation_group(): void {
}

// @mago-expect lint:drupal/function-comment
function inline_comment_directive_shapes(): void {
  // cspell:ignore corpusword otherword
  $a = 1;

  // @mago-expect lint:drupal/inline-comment-punctuation
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

/**
 * Does something that got replaced, with an example of the replacement.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar() instead.
 * @code
 * bar($thing);
 * @endcode
 *
 * @see https://www.drupal.org/node/1234567
 */
function deprecated_tag_with_an_example(): void {
}

// @mago-expect lint:drupal/doc-comment
/**
 * Does something that got replaced, with another tag before the link.
 *
 * @deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar() instead.
 * @throws \RuntimeException
 *
 * @see https://www.drupal.org/node/1234567
 */
function deprecated_tag_with_a_tag_before_the_see(): void {
}
