<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/nullable-param-tag
/**
 * Documents an untyped NULL default without null.
 *
 * @param string $rel
 *   (optional) The link relationship type.
 */
function nullable_param_plain($rel = NULL): void {
}

// @mago-expect lint:drupal/nullable-param-tag
/**
 * Writes the default in upper case, the way Drupal does.
 *
 * @param int $count
 *   (optional) How many to show.
 */
function nullable_param_upper($count = NULL): void {
}

// @mago-expect lint:drupal/nullable-param-tag
/**
 * Has the null only inside a generic.
 *
 * @param array<string|null> $names
 *   (optional) The names, some of them unknown.
 */
function nullable_param_generic($names = NULL): void {
}

// @mago-expect lint:drupal/nullable-param-tag
// @mago-expect lint:drupal/function-comment
/**
 * Splits the type over two lines, so the fix is left out.
 *
 * @param array{
 *   label: string,
 * } $options
 *   (optional) The options.
 */
function nullable_param_two_lines($options = NULL): void {
}

// @mago-expect lint:drupal/nullable-param-tag
/**
 * Has a by-reference parameter and a fully qualified default.
 *
 * @param string[] $list
 *   (optional) The list to fill.
 */
function nullable_param_reference(&$list = \NULL): void {
}

/**
 * Admits null in each of the ways the rule accepts.
 *
 * @param string|null $a
 *   (optional) A union with null last.
 * @param null|string $b
 *   (optional) A union with null first.
 * @param ?string $c
 *   (optional) The shorthand.
 * @param mixed $d
 *   (optional) Any value.
 * @param (string|null)|int $e
 *   (optional) A null inside parentheses.
 * @param string $f
 *   (optional) A parameter with a native type.
 * @param string $g
 *   A parameter without a NULL default.
 */
function nullable_param_accepted(
  $a = NULL,
  $b = NULL,
  $c = NULL,
  $d = NULL,
  $e = NULL,
  ?string $f = NULL,
  $g = 'x',
): void {
}

/**
 * Takes its type from a template.
 *
 * @param T $value
 *   (optional) The value.
 *
 * @template T
 */
function nullable_param_template($value = NULL): void {
}

/**
 * Lets the PHPStan tag decide over the plain one.
 *
 * @param string $value
 *   (optional) The value.
 *
 * @phpstan-param string|null $value
 */
function nullable_param_phpstan($value = NULL): void {
}

/**
 * Has a NULL default but no tag for the parameter.
 */
function nullable_param_undocumented($value = NULL): void {
}

/**
 * Stands in for a class whose constructor promotes a property.
 */
final class NullableParamPromoted {

  // @mago-expect lint:drupal/nullable-param-tag
  /**
   * Keeps the label it is given.
   *
   * @param string $label
   *   (optional) The label.
   */
  public function __construct(
    public $label = NULL,
  ) {}

  /**
   * Inherits its documentation.
   *
   * {@inheritdoc}
   */
  public function inherited($value = NULL): void {
  }

}
