<?php

declare(strict_types=1);

namespace Drupal\ignores;

use Drupal\Core\Messenger\MessengerInterface;

/**
 * Properties and calls under phpcs comments, which --phpcs-ignores reads.
 */
final class PhpcsComments {

  /**
   * A comment above the property names the full code.
   *
   * @var int
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public $full_code = 1;

  /**
   * The sniff covers its codes, and a note can follow the codes.
   *
   * @var int
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName -- Mirrors the data.
  public $sniff_name = 1;

  /**
   * The standard covers its codes.
   *
   * @var int
   */
  // phpcs:ignore Drupal
  public $standard_name = 1;

  /**
   * A trailing comment covers its line, for every code when it names none.
   *
   * @var int
   */
  public $trailing_comment = 1; // phpcs:ignore

  /**
   * The old form covers the next line too.
   *
   * @var int
   */
  // @codingStandardsIgnoreLine
  public $old_form = 1;

  /**
   * A comment for another code keeps the issue.
   *
   * @var int
   */
  // @mago-expect lint:drupal/property-name
  // phpcs:ignore Drupal.Commenting.VariableComment.MissingVar
  public $other_code = 1;

  /**
   * A comment covers only the line after it.
   *
   * @var int
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName

  // @mago-expect lint:drupal/property-name
  public $blank_line_between = 1;

  // phpcs:disable Drupal.NamingConventions.ValidVariableName.LowerCamelName

  /**
   * A region covers every line up to its end.
   *
   * @var int
   */
  public $in_region = 1;

  // phpcs:enable

  /**
   * The region has ended.
   *
   * @var int
   */
  // @mago-expect lint:drupal/property-name
  public $after_region = 1;

  // phpcs:disable Drupal.NamingConventions
  // phpcs:enable Drupal.NamingConventions.ValidVariableName

  /**
   * A sniff enabled again inside a disabled category is checked.
   *
   * @var int
   */
  // @mago-expect lint:drupal/property-name
  public $enabled_again = 1;

  // phpcs:enable

  /**
   * A docblock line covers the next line, where Coder reports the missing tag.
   *
   * phpcs:ignore Drupal.Commenting.VariableComment.MissingVar
   */
  public $docblockLine;

  public function __construct(
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * Coder 8 names the variable sniff in its own standard.
   */
  public function transaction(): void {
    // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis
    $unused = 1;
  }

  /**
   * The analyzer checks that port a sniff read the comments too.
   */
  public function messages(): void {
    // phpcs:ignore DrupalPractice.FunctionCalls.MessageT.ErrorMessage
    $this->messenger->addMessage('Saved.');
    // @mago-expect analysis:drupal/untranslated-message
    $this->messenger->addStatus('Done.');
  }

}
